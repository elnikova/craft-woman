<?php

namespace Modules\Identity\Domain\Exceptions;

use DomainException;
use Modules\Identity\Domain\MakerStatus;

final class InvalidMakerStatusTransition extends DomainException
{
    /** @param array<string, string> $params */
    private function __construct(string $message, public readonly string $key, public readonly array $params)
    {
        parent::__construct($message);
    }

    public static function between(MakerStatus $from, MakerStatus $to): self
    {
        return new self(
            "Maker profile cannot move from [{$from->value}] to [{$to->value}].",
            'maker.invalid_transition',
            ['from' => $from->value, 'to' => $to->value],
        );
    }
}
