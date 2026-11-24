<?php

namespace Modules\Orders\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['product_uuid', 'product_variant_uuid', 'quantity', 'unit_price_amount_minor', 'currency'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_amount_minor' => 'integer'];
    }
}
