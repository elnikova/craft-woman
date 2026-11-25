<?php

namespace Modules\Education\Application\Contracts;

/** A course as the Orders module sees it when it is put in a cart or bought. */
final readonly class CourseLine
{
    public function __construct(
        public string $courseUuid,
        public string $makerUuid,
        public string $name,
        public string $slug,
        public ?string $imageUrl,
        public int $priceMinor,
        public string $currency,
        public bool $isPurchasable,
    ) {}
}
