<?php

namespace Modules\Marketplace\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Marketplace\Domain\Currency;
use Modules\Marketplace\Domain\ValueObjects\Money;

/**
 * Purchasable variation of a physical product; inventory lives here (schema §4.2).
 *
 * @property int $stock
 * @property ?int $price_amount_minor
 */
class ProductVariant extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['sku', 'name', 'price_amount_minor', 'currency', 'stock', 'weight_grams', 'is_default', 'status'];

    protected $attributes = ['status' => 'active', 'stock' => 0, 'is_default' => false];

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'price_amount_minor' => 'integer',
            'stock' => 'integer',
            'weight_grams' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function effectivePrice(Product $product): Money
    {
        return new Money($this->price_amount_minor ?? $product->price_amount_minor, $product->currency);
    }

    public function isPurchasable(): bool
    {
        return $this->isActive() && $this->stock > 0;
    }
}
