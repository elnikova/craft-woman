<?php

namespace Modules\Identity\Domain\Exceptions;

use DomainException;
use Modules\Identity\Domain\MakerStatus;

final class MakerProfileNotEditable extends DomainException
{
    /** @param array<string, string> $params */
    private function __construct(string $message, public readonly string $key, public readonly array $params)
    {
        parent::__construct($message);
    }

    public static function inStatus(MakerStatus $status): self
    {
        return new self("Maker profile cannot be edited while in [{$status->value}] status.", 'maker.not_editable', ['status' => $status->value]);
    }
}
