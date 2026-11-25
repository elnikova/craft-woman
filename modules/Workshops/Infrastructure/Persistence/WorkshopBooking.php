<?php

namespace Modules\Workshops\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Workshops\Domain\BookingStatus;

/** Seats for the whole workshop bought by one order line. */
class WorkshopBooking extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['user_uuid', 'workshop_uuid', 'order_item_uuid', 'seats', 'status', 'booked_at', 'confirmed_at', 'cancelled_at', 'cancel_reason'];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'seats' => 'integer',
            'booked_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workshop, $this> */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'workshop_uuid')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }
}
