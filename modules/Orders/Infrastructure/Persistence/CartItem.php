<?php

namespace Modules\Orders\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Orders\Application\Purchasables\Purchasable;

class CartItem extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['product_uuid', 'product_variant_uuid', 'course_uuid', 'workshop_uuid', 'quantity', 'unit_price_amount_minor', 'currency'];

    /** Key matching PurchasableCatalog::forItems() */
    public function lineKey(): string
    {
        return match (true) {
            $this->course_uuid !== null => Purchasable::key(Purchasable::COURSE, $this->course_uuid),
            $this->workshop_uuid !== null => Purchasable::key(Purchasable::WORKSHOP, $this->workshop_uuid),
            default => Purchasable::key(Purchasable::PRODUCT, (string) $this->product_uuid, $this->product_variant_uuid),
        };
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_amount_minor' => 'integer'];
    }
}
