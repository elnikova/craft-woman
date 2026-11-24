<?php

namespace Modules\Identity\Domain\Exceptions;

use DomainException;
use Modules\Identity\Domain\MakerStatus;

final class InvalidMakerStatusTransition extends DomainException
{
    public static function between(MakerStatus $from, MakerStatus $to): self
    {
        return new self("Maker profile cannot move from [{$from->value}] to [{$to->value}].");
    }
}
