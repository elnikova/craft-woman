<?php

namespace Modules\Workshops\Presentation\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Workshops\Domain\BookingStatus;
use Modules\Workshops\Infrastructure\Persistence\Workshop;
use Modules\Workshops\Infrastructure\Persistence\WorkshopBooking;
use Modules\Workshops\Infrastructure\Persistence\WorkshopSession;

/**
 * Shapes workshops for the API: card (lists), detail (public page), editor (maker/admin),
 * booking (participant's view). The meeting link, exact address and note for participants
 * are only in `private` — sent to participants, the owner and admins.
 */
final class WorkshopPresenter
{
    public function __construct(private readonly MakerDirectory $makers) {}

    /** @return array<string, mixed> */
    public function card(Workshop $w, ?array $maker = null): array
    {
        $w->loadMissing('categories', 'sessions');
        $category = $w->categories->first();
        $taken = $w->seatsTaken();

        return [
            'uuid' => $w->uuid,
            'slug' => $w->slug,
            ...$w->localized(),
            'cover_url' => $w->coverUrl(),
            'price' => ['amount_minor' => $w->price_amount_minor, 'currency' => $w->currency],
            'format' => $w->format->value,
            'timezone' => $w->timezone,
            'city' => $w->location_city,
            'country_code' => $w->location_country_code,
            'capacity' => $w->capacity,
            'seats_left' => max(0, $w->capacity - $taken),
            'starts_at' => $w->startsAt()?->toIso8601String(),
            'ends_at' => $w->endsAt()?->toIso8601String(),
            'sessions_count' => $w->sessions->count(),
            'is_bookable' => $w->isBookable(),
            'has_ended' => $w->hasEnded(),
            'category' => $category ? ['uuid' => $category->uuid, 'name' => $category->translate('name'), 'slug' => $category->slug] : null,
            'maker' => $maker ?? $this->makers->summaries([$w->maker_profile_uuid])[$w->maker_profile_uuid]?->toArray(),
            'published_at' => $w->published_at,
        ];
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function page(LengthAwarePaginator $page, ?callable $extra = null): array
    {
        $makers = $this->makers->summaries($page->getCollection()->pluck('maker_profile_uuid')->unique()->values()->all());

        return [
            'data' => $page->getCollection()->map(fn (Workshop $w) => [
                ...$this->card($w, $makers[$w->maker_profile_uuid]?->toArray()),
                ...($extra ? $extra($w) : []),
            ])->values()->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Workshop $w, bool $withPrivate): array
    {
        return [
            ...$this->card($w),
            'status' => $w->status->value,
            'cancel_reason' => $w->status->value === 'cancelled' ? $w->review_note : null,
            'sessions' => $w->sessions->map(fn (WorkshopSession $s) => $this->session($w, $s))->values(),
            'private' => $withPrivate ? $this->private($w) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function editor(Workshop $w, array $missing): array
    {
        $w->loadMissing('sessions', 'categories');

        return [
            ...$this->detail($w, true),
            'name' => $w->name,
            'description' => $w->description,
            'participant_note' => $w->participant_note,
            'content_locale' => $w->sourceLocale(),
            'translations' => (object) ($w->translations ?? []),
            'review_note' => $w->review_note,
            'is_editable' => $w->status->isEditable(),
            'is_deletable' => $w->status->isDeletable() && ! $w->bookings()->exists(),
            'missing' => $missing,
            'seats_booked' => $w->seatsTaken(),
            'location_address' => $w->location_address,
            'external_url' => $w->external_url,
        ];
    }

    /** A participant's booking with everything needed to attend. */
    public function booking(WorkshopBooking $b): array
    {
        $w = $b->workshop;
        $active = $b->status === BookingStatus::Confirmed;

        return [
            'uuid' => $b->uuid,
            'seats' => $b->seats,
            'status' => $b->status->value,
            'booked_at' => $b->booked_at,
            'cancelled_at' => $b->cancelled_at,
            'cancel_reason' => $b->cancel_reason,
            'workshop' => [
                ...$this->card($w),
                'sessions' => $w->sessions->map(fn (WorkshopSession $s) => $this->session($w, $s))->values(),
                'private' => $active ? $this->private($w) : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function session(Workshop $w, WorkshopSession $s): array
    {
        return [
            'uuid' => $s->uuid,
            'starts_at' => $s->starts_at->toIso8601String(),
            'ends_at' => $s->ends_at->toIso8601String(),
            // Wall-clock values in the workshop's timezone, for <input type="datetime-local">
            'starts_local' => $s->starts_at->setTimezone($w->timezone)->format('Y-m-d\TH:i'),
            'ends_local' => $s->ends_at->setTimezone($w->timezone)->format('Y-m-d\TH:i'),
        ];
    }

    /** @return array<string, mixed> */
    private function private(Workshop $w): array
    {
        return [
            'address' => $w->location_address,
            'meeting_url' => $w->external_url,
            'note' => $w->translate('participant_note'),
        ];
    }
}
