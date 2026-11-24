<?php

namespace Modules\Identity\Domain\Exceptions;

use DomainException;
use Modules\Identity\Domain\MakerStatus;

final class MakerProfileNotEditable extends DomainException
{
    public static function inStatus(MakerStatus $status): self
    {
        return new self("Maker profile cannot be edited while in [{$status->value}] status.");
    }
}
