<?php

namespace Modules\Identity\Domain\Exceptions;

use DomainException;

final class MakerProfileAlreadyExists extends DomainException
{
    /** @param array<string, string> $params */
    private function __construct(string $message, public readonly string $key, public readonly array $params = [])
    {
        parent::__construct($message);
    }

    public static function forUser(string $userUuid): self
    {
        return new self("User [{$userUuid}] already has a Maker profile.", 'maker.already_exists');
    }
}
