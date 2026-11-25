<?php

namespace Modules\Workshops\Infrastructure\Persistence;

use App\Support\HasTranslations;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Marketplace\Infrastructure\Persistence\Category;
use Modules\Workshops\Domain\BookingStatus;
use Modules\Workshops\Domain\Seats;
use Modules\Workshops\Domain\WorkshopFormat;
use Modules\Workshops\Domain\WorkshopStatus;

/**
 * @property string $uuid
 * @property string $maker_profile_uuid
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property int $capacity
 * @property string $timezone
 * @property WorkshopStatus $status
 * @property WorkshopFormat $format
 */
class Workshop extends Model
{
    use HasFactory, HasTranslations, HasUuids, SoftDeletes;

    /** @var list<string> */
    protected array $translatable = ['name', 'description', 'participant_note'];

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'maker_profile_uuid', 'name', 'slug', 'description', 'price_amount_minor', 'currency', 'format', 'timezone', 'capacity',
        'location_country_code', 'location_city', 'location_address', 'external_url', 'participant_note', 'content_locale',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkshopStatus::class,
            'format' => WorkshopFormat::class,
            'price_amount_minor' => 'integer',
            'capacity' => 'integer',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function newFactory(): WorkshopFactory
    {
        return WorkshopFactory::new();
    }

    /** @return HasMany<WorkshopSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(WorkshopSession::class, 'workshop_uuid')->orderBy('starts_at');
    }

    /** @return HasMany<WorkshopBooking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(WorkshopBooking::class, 'workshop_uuid');
    }

    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable', 'categorizables', 'categorizable_uuid', 'category_uuid')
            ->withPivot('created_at');
    }

    /** @param Builder<self> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), WorkshopStatus::Published);
    }

    /** Workshops whose first session hasn't started yet */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereExists(fn ($q) => $q->selectRaw('1')->from('workshop_sessions')
            ->whereColumn('workshop_sessions.workshop_uuid', 'workshops.uuid'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('workshop_sessions')
                ->whereColumn('workshop_sessions.workshop_uuid', 'workshops.uuid')
                ->where('starts_at', '<=', now()));
    }

    public function seatsTaken(): int
    {
        return (int) $this->bookings()->whereIn('status', BookingStatus::holding())->sum('seats');
    }

    public function seatsLeft(): int
    {
        return Seats::left($this->capacity, $this->seatsTaken());
    }

    public function startsAt(): ?CarbonImmutable
    {
        $first = $this->relationLoaded('sessions') ? $this->sessions->first()?->starts_at : $this->sessions()->min('starts_at');

        return $first ? CarbonImmutable::parse($first) : null;
    }

    public function endsAt(): ?CarbonImmutable
    {
        $last = $this->relationLoaded('sessions') ? $this->sessions->max('ends_at') : $this->sessions()->max('ends_at');

        return $last ? CarbonImmutable::parse($last) : null;
    }

    /** Booking closes when the first session starts */
    public function hasStarted(): bool
    {
        $start = $this->startsAt();

        return $start !== null && $start->isPast();
    }

    public function hasEnded(): bool
    {
        $end = $this->endsAt();

        return $end !== null && $end->isPast();
    }

    public function isBookable(): bool
    {
        return $this->status === WorkshopStatus::Published && $this->startsAt() !== null && ! $this->hasStarted();
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
