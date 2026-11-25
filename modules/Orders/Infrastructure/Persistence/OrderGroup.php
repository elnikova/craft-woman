<?php

namespace Modules\Orders\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Orders\Domain\GroupStatus;

/** @property GroupStatus $status */
class OrderGroup extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'maker_profile_uuid', 'maker_name', 'subtotal_amount_minor', 'delivery_amount_minor',
        'total_amount_minor', 'status', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => GroupStatus::class,
            'subtotal_amount_minor' => 'integer',
            'delivery_amount_minor' => 'integer',
            'total_amount_minor' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_uuid');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_group_uuid')->orderBy('created_at');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class, 'order_group_uuid');
    }

    public function hasPhysical(): bool
    {
        return $this->items->contains(fn (OrderItem $i) => ! $i->isDigital());
    }
}
