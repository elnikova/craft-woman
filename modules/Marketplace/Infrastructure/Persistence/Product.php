<?php

namespace Modules\Marketplace\Infrastructure\Persistence;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Marketplace\Domain\Currency;
use Modules\Marketplace\Domain\ProductStatus;
use Modules\Marketplace\Domain\ProductType;
use Modules\Marketplace\Domain\ValueObjects\Money;

/**
 * @property string $uuid
 * @property string $maker_profile_uuid
 * @property ProductType $type
 * @property ProductStatus $status
 * @property Currency $currency
 * @property int $price_amount_minor
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** categorizable_type value (schema §4.6) */
    public const MORPH = 'product';

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'maker_profile_uuid', 'type', 'name', 'slug', 'description',
        'price_amount_minor', 'currency', 'production_time_days', 'shipping_info',
    ];

    protected $attributes = ['status' => 'draft', 'currency' => 'EUR', 'price_amount_minor' => 0];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'currency' => Currency::class,
            'price_amount_minor' => 'integer',
            'production_time_days' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function price(): Money
    {
        return new Money($this->price_amount_minor, $this->currency);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_uuid')->orderByDesc('is_default')->orderBy('created_at');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_uuid')->orderBy('sort_order');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class, 'product_uuid')->orderBy('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tags', 'product_uuid', 'tag_uuid');
    }

    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable', 'categorizables', 'categorizable_uuid', 'category_uuid')
            ->withPivot('created_at');
    }

    /** @param Builder<self> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Published);
    }
}
