<?php

namespace Modules\Identity\Domain\Exceptions;

use DomainException;

final class MakerProfileAlreadyExists extends DomainException
{
    public static function forUser(string $userUuid): self
    {
        return new self("User [{$userUuid}] already has a Maker profile.");
    }
}
