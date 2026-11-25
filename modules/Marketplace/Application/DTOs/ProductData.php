<?php

namespace Modules\Marketplace\Application\DTOs;

use Modules\Marketplace\Domain\Currency;
use Modules\Marketplace\Domain\ProductType;

/** Editable product fields as submitted by the Maker. Null = "not provided, keep as is" for optional groups. */
final readonly class ProductData
{
    /**
     * @param  ?list<string>  $tags
     * @param  ?list<array{uuid?: ?string, name: string, sku?: ?string, price_amount_minor?: ?int, stock: int, weight_grams?: ?int, status?: string}>  $variants
     */
    public function __construct(
        public ?ProductType $type,
        public string $name,
        public ?string $description,
        public int $priceMinor,
        public Currency $currency,
        public ?int $productionTimeDays,
        public ?string $shippingInfo,
        public ?string $categoryUuid,
        public ?array $tags,
        public ?array $variants,
    ) {}

    /** @param array<string, mixed> $v validated request data */
    public static function fromArray(array $v): self
    {
        return new self(
            type: isset($v['type']) ? ProductType::from($v['type']) : null,
            name: trim((string) $v['name']),
            description: isset($v['description']) ? trim((string) $v['description']) : null,
            priceMinor: (int) ($v['price_amount_minor'] ?? 0),
            currency: Currency::from($v['currency'] ?? 'EUR'),
            productionTimeDays: isset($v['production_time_days']) ? (int) $v['production_time_days'] : null,
            shippingInfo: $v['shipping_info'] ?? null,
            categoryUuid: $v['category_uuid'] ?? null,
            tags: array_key_exists('tags', $v) ? array_values($v['tags'] ?? []) : null,
            variants: array_key_exists('variants', $v) ? array_values($v['variants'] ?? []) : null,
        );
    }
}
