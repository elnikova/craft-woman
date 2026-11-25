<?php

namespace Modules\Orders\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['product_uuid', 'product_variant_uuid', 'course_uuid', 'quantity', 'unit_price_amount_minor', 'currency'];

    /** Key matching PurchasableCatalog::forItems() */
    public function lineKey(): string
    {
        return $this->course_uuid
            ? \Modules\Orders\Application\Purchasables\Purchasable::key('course', $this->course_uuid)
            : \Modules\Orders\Application\Purchasables\Purchasable::key('product', (string) $this->product_uuid, $this->product_variant_uuid);
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_amount_minor' => 'integer'];
    }
}
