<?php

namespace Modules\Marketplace\Application\Contracts;

/** Current purchasable state of a product (+ variant), as other modules (Orders) see it. */
final readonly class PurchasableLine
{
    public function __construct(
        public string $productUuid,
        public ?string $variantUuid,
        public string $makerUuid,
        public bool $isDigital,
        public string $productName,
        public ?string $variantName,
        public string $slug,
        public ?string $imageUrl,
        public int $unitPriceMinor,
        public string $currency,
        /** null for digital products (no physical stock) */
        public ?int $availableStock,
        /** published product, active variant */
        public bool $isPurchasable,
    ) {}
}
