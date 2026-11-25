<?php

namespace Modules\Workshops\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Identity\Application\Contracts\MakerSummary;
use Modules\Workshops\Domain\BookingStatus;
use Modules\Workshops\Domain\Exceptions\WorkshopRuleViolation;
use Modules\Workshops\Domain\WorkshopFormat;
use Modules\Workshops\Domain\WorkshopReadiness;
use Modules\Workshops\Domain\WorkshopStatus;
use Modules\Workshops\Infrastructure\Persistence\Workshop;
use Modules\Workshops\Infrastructure\Persistence\WorkshopSession;

/** Workshop details, sessions and lifecycle for Makers and moderators. */
final class WorkshopService
{
    private const FIELDS = [
        'name', 'description', 'price_amount_minor', 'currency', 'format', 'timezone', 'capacity',
        'location_country_code', 'location_city', 'location_address', 'external_url', 'participant_note', 'content_locale',
    ];

    public function __construct(private readonly BookingService $bookings) {}

    /** @param array<string, mixed> $data */
    public function create(MakerSummary $maker, array $data): Workshop
    {
        if (! $maker->isApproved) {
            throw WorkshopRuleViolation::makerNotApproved();
        }

        return DB::transaction(function () use ($maker, $data) {
            $workshop = new Workshop(['maker_profile_uuid' => $maker->uuid, 'slug' => $this->uniqueSlug($data['name'])]);
            $workshop->status = WorkshopStatus::Draft;
            $workshop->location_country_code ??= $maker->countryCode;
            $this->apply($workshop, $data);
            $workshop->save();
            $this->syncCategory($workshop, $data);

            return $workshop->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Workshop $workshop, array $data): Workshop
    {
        $this->assertEditable($workshop);

        return DB::transaction(function () use ($workshop, $data) {
            $booked = $workshop->seatsTaken();
            if (isset($data['capacity']) && (int) $data['capacity'] < $booked) {
                throw WorkshopRuleViolation::capacityBelowBooked($booked);
            }
            if (isset($data['format']) && $data['format'] !== $workshop->format->value && $booked > 0) {
                throw WorkshopRuleViolation::formatLocked();
            }
            if ($workshop->published_at === null && isset($data['name']) && $workshop->name !== $data['name']) {
                $workshop->slug = $this->uniqueSlug($data['name'], ignore: $workshop->uuid);
            }
            $this->apply($workshop, $data);
            $workshop->save();
            $this->syncCategory($workshop, $data);

            return $workshop->refresh();
        });
    }

    public function setCover(Workshop $workshop, UploadedFile $file): Workshop
    {
        $this->assertEditable($workshop);
        $old = $workshop->cover_path;
        $workshop->cover_path = $file->store("workshop-covers/{$workshop->uuid}", 'public');
        $workshop->save();
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return $workshop;
    }

    /** Local date-time strings (no offset) are read in the workshop's timezone. */
    public function addSession(Workshop $workshop, string $startsAt, string $endsAt): WorkshopSession
    {
        $this->assertEditable($workshop);
        [$start, $end] = $this->times($workshop, $startsAt, $endsAt);

        return $workshop->sessions()->create(['starts_at' => $start, 'ends_at' => $end]);
    }

    public function updateSession(WorkshopSession $session, string $startsAt, string $endsAt): WorkshopSession
    {
        $this->assertEditable($session->workshop);
        [$start, $end] = $this->times($session->workshop, $startsAt, $endsAt);
        $session->update(['starts_at' => $start, 'ends_at' => $end]);

        return $session;
    }

    public function deleteSession(WorkshopSession $session): void
    {
        $workshop = $session->workshop;
        $this->assertEditable($workshop);
        if ($workshop->sessions()->count() === 1 && $workshop->seatsTaken() > 0) {
            throw WorkshopRuleViolation::lastSession();
        }
        $session->delete();
    }

    /** @return list<string> */
    public function missingForReview(Workshop $workshop): array
    {
        $workshop->load('sessions');

        return WorkshopReadiness::missing([
            'name' => $workshop->name,
            'description' => $workshop->description,
            'price_minor' => $workshop->price_amount_minor,
            'capacity' => $workshop->capacity,
            'format' => $workshop->format,
            'country' => $workshop->location_country_code,
            'city' => $workshop->location_city,
            'address' => $workshop->location_address,
            'sessions' => $workshop->sessions->count(),
            'first_start_in_future' => $workshop->startsAt()?->isFuture() ?? false,
            'invalid_sessions' => $workshop->sessions->filter(fn (WorkshopSession $s) => $s->ends_at <= $s->starts_at)->count(),
        ]);
    }

    public function submitForReview(Workshop $workshop): Workshop
    {
        $next = $workshop->status->transitionTo(WorkshopStatus::PendingReview);
        if (($missing = $this->missingForReview($workshop)) !== []) {
            throw WorkshopRuleViolation::notReady($missing);
        }

        return $this->setStatus($workshop, $next, null);
    }

    public function approve(Workshop $workshop): Workshop
    {
        $workshop->status = $workshop->status->transitionTo(WorkshopStatus::Published);
        $workshop->review_note = null;
        $workshop->published_at ??= now();
        $workshop->save();

        return $workshop;
    }

    public function reject(Workshop $workshop, string $note): Workshop
    {
        return $this->setStatus($workshop, $workshop->status->transitionTo(WorkshopStatus::Rejected), $note);
    }

    /** Off sale; existing participants keep their seats. */
    public function archive(Workshop $workshop, ?string $note = null): Workshop
    {
        return $this->setStatus($workshop, $workshop->status->transitionTo(WorkshopStatus::Archived), $note);
    }

    public function relist(Workshop $workshop): Workshop
    {
        return $this->setStatus($workshop, $workshop->status->transitionTo(WorkshopStatus::Draft), null);
    }

    /** The maker calls the whole workshop off: every booking is cancelled with the reason. Refunds come with Stripe. */
    public function cancel(Workshop $workshop, string $reason): Workshop
    {
        return DB::transaction(function () use ($workshop, $reason) {
            $workshop->status = $workshop->status->transitionTo(WorkshopStatus::Cancelled);
            $workshop->review_note = $reason;
            $workshop->cancelled_at = now();
            $workshop->save();

            $workshop->bookings()->whereIn('status', BookingStatus::holding())
                ->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 500)]);

            return $workshop;
        });
    }

    public function delete(Workshop $workshop): void
    {
        if (! $workshop->status->isDeletable() || $workshop->bookings()->exists()) {
            throw WorkshopRuleViolation::notDeletable();
        }
        DB::transaction(function () use ($workshop) {
            if ($workshop->cover_path) {
                Storage::disk('public')->delete($workshop->cover_path);
            }
            $workshop->categories()->detach();
            $workshop->forceDelete();
        });
    }

    public function assertEditable(Workshop $workshop): void
    {
        if (! $workshop->status->isEditable()) {
            throw WorkshopRuleViolation::notEditable($workshop->status);
        }
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} in UTC */
    private function times(Workshop $workshop, string $startsAt, string $endsAt): array
    {
        $start = CarbonImmutable::parse($startsAt, $workshop->timezone)->utc();
        $end = CarbonImmutable::parse($endsAt, $workshop->timezone)->utc();
        if ($end <= $start || $start->diffInHours($end) > 24) {
            throw WorkshopRuleViolation::invalidSession();
        }

        return [$start, $end];
    }

    /** @param array<string, mixed> $data */
    private function apply(Workshop $workshop, array $data): void
    {
        $workshop->fill(array_intersect_key($data, array_flip(self::FIELDS)));
        if ($workshop->format === WorkshopFormat::Online) {
            $workshop->location_address = null;
        } else {
            $workshop->external_url = null;
        }
        if (array_key_exists('translations', $data)) {
            $workshop->translations = $workshop->cleanTranslations($data['translations'] ?? []) ?: null;
        }
    }

    /** @param array<string, mixed> $data */
    private function syncCategory(Workshop $workshop, array $data): void
    {
        if (array_key_exists('category_uuid', $data)) {
            $workshop->categories()->sync($data['category_uuid'] ? [$data['category_uuid'] => ['created_at' => now()]] : []);
        }
    }

    private function setStatus(Workshop $workshop, WorkshopStatus $status, ?string $note): Workshop
    {
        $workshop->status = $status;
        $workshop->review_note = $note;
        $workshop->save();

        return $workshop;
    }

    private function uniqueSlug(string $name, ?string $ignore = null): string
    {
        $base = Str::slug($name) ?: 'workshop';
        $slug = $base;
        $i = 2;
        while (Workshop::withTrashed()->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
