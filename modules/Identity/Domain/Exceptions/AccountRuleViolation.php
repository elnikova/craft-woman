<?php

namespace Modules\Identity\Domain\Exceptions;

use DomainException;

/** Rules for managing user accounts. Translated via lang/{locale}/domain.php → account.* */
final class AccountRuleViolation extends DomainException
{
    /** @param array<string, string> $params */
    private function __construct(string $message, public readonly string $key, public readonly array $params = [])
    {
        parent::__construct($message);
    }

    public static function notOnYourself(): self
    {
        return new self('Administrators cannot block themselves or change their own role.', 'account.not_on_yourself');
    }

    public static function lastAdmin(): self
    {
        return new self('The platform must keep at least one active administrator.', 'account.last_admin');
    }

    public static function makerRole(): self
    {
        return new self('Maker accounts keep the maker role; their access is managed through the maker profile.', 'account.maker_role');
    }

    public static function alreadyInState(string $status): self
    {
        return new self("The account is already {$status}.", 'account.already', ['status' => 'account_'.$status]);
    }
}
