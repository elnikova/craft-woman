<?php

namespace Modules\Orders\Application\Purchasables;

/**
 * Anything that can sit in a cart, as Orders needs it: a product (+ variant) from
 * Marketplace, a course from Education or workshop seats from Workshops. Built by PurchasableCatalog.
 */
final readonly class Purchasable
{
    public const PRODUCT = 'product';

    public const COURSE = 'course';

    public const WORKSHOP = 'workshop';

    public function __construct(
        public string $kind,
        public string $itemUuid,
        public ?string $variantUuid,
        public string $makerUuid,
        /** nothing to ship (digital product, course, workshop seats): prepaid only */
        public bool $isDigital,
        public string $name,
        public ?string $variantName,
        public string $slug,
        public ?string $imageUrl,
        public int $unitPriceMinor,
        public string $currency,
        /** physical stock or workshop seats left; null when unlimited */
        public ?int $availableStock,
        public bool $isPurchasable,
        /** Workshops: first session start (ISO 8601, workshop's offset) */
        public ?string $startsAt = null,
    ) {}

    public static function key(string $kind, string $itemUuid, ?string $variantUuid = null): string
    {
        return "{$kind}:{$itemUuid}:{$variantUuid}";
    }

    public function isCourse(): bool
    {
        return $this->kind === self::COURSE;
    }

    public function isWorkshop(): bool
    {
        return $this->kind === self::WORKSHOP;
    }

    /** Digital products and courses are bought once; goods and workshop seats come in quantities */
    public function isSingleUnit(): bool
    {
        return $this->isDigital && ! $this->isWorkshop();
    }
}
