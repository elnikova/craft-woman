<?php

namespace Modules\Orders\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Delivery of one Maker's part of an order (schema §7.6). Provider integrations come later. */
class Delivery extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'recipient_name', 'phone', 'country_code', 'city', 'address', 'postal_code',
        'tracking_number', 'status', 'shipped_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return ['shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
    }
}
