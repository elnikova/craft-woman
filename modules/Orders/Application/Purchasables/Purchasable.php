<?php

namespace Modules\Orders\Application\Purchasables;

/**
 * Anything that can sit in a cart, as Orders needs it: a product (+ variant) from
 * Marketplace or a course from Education. Built by PurchasableCatalog.
 */
final readonly class Purchasable
{
    public const PRODUCT = 'product';

    public const COURSE = 'course';

    public function __construct(
        public string $kind,
        public string $itemUuid,
        public ?string $variantUuid,
        public string $makerUuid,
        /** digital product or course: nothing to ship, bought once, prepaid */
        public bool $isDigital,
        public string $name,
        public ?string $variantName,
        public string $slug,
        public ?string $imageUrl,
        public int $unitPriceMinor,
        public string $currency,
        /** null when there is no physical stock */
        public ?int $availableStock,
        public bool $isPurchasable,
    ) {}

    public static function key(string $kind, string $itemUuid, ?string $variantUuid = null): string
    {
        return "{$kind}:{$itemUuid}:{$variantUuid}";
    }

    public function isCourse(): bool
    {
        return $this->kind === self::COURSE;
    }
}
