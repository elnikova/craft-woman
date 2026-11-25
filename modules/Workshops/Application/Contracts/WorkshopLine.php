<?php

namespace Modules\Workshops\Application\Contracts;

/** A workshop as the Orders module sees it when it is put in a cart or bought. */
final readonly class WorkshopLine
{
    public function __construct(
        public string $workshopUuid,
        public string $makerUuid,
        public string $name,
        public string $slug,
        public ?string $imageUrl,
        public int $priceMinor,
        public string $currency,
        public bool $isPurchasable,
        public int $seatsLeft,
        /** First session start in ISO 8601 with the workshop's offset, for cart and order lines */
        public ?string $startsAt,
    ) {}
}
