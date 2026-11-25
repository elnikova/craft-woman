<?php

namespace Modules\Marketplace\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\Marketplace\Domain\Currency;

/** Money as integer minor units + currency. Never floats (domain-model.md §2.6–2.7). */
final readonly class Money
{
    public function __construct(public int $amountMinor, public Currency $currency)
    {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function multiply(int $quantity): self
    {
        return new self($this->amountMinor * $quantity, $this->currency);
    }

    /** @return array{amount_minor: int, currency: string} */
    public function toArray(): array
    {
        return ['amount_minor' => $this->amountMinor, 'currency' => $this->currency->value];
    }
}
