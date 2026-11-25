<?php

namespace Modules\Orders\Domain;

/** Facts about one Maker's part of the cart, as needed by the payment and delivery rules. */
final readonly class CheckoutGroupFacts
{
    public function __construct(
        public string $makerUuid,
        public bool $hasPhysical,
        public bool $hasDigital,
        public bool $isInternational,
        public bool $makerAllowsCod,
        public bool $shipsInternationally,
    ) {}
}
