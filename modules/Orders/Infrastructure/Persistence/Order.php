<?php

namespace Modules\Orders\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Orders\Domain\OrderStatus;
use Modules\Orders\Domain\PaymentMethod;

/**
 * @property OrderStatus $status
 * @property PaymentMethod $payment_method
 */
class Order extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'user_uuid', 'order_number', 'currency', 'subtotal_amount_minor', 'delivery_amount_minor',
        'total_amount_minor', 'status', 'payment_method', 'billing_email', 'placed_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal_amount_minor' => 'integer',
            'delivery_amount_minor' => 'integer',
            'total_amount_minor' => 'integer',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(OrderGroup::class, 'order_uuid')->orderBy('created_at');
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    /** Recompute the overall status from the Makers' parts. */
    public function syncStatus(): void
    {
        $this->loadMissing('groups');
        $this->status = OrderStatus::derive($this->groups->pluck('status')->all());
        $this->save();
    }
}
