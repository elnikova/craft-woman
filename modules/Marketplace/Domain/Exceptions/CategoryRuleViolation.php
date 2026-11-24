<?php

namespace Modules\Marketplace\Domain\Exceptions;

use DomainException;

/**
 * Category tree rules; rendered as 409 Conflict.
 * The domain stays framework-free: it exposes a translation key + params,
 * and the HTTP layer translates them (lang/{uk,en}/domain.php).
 */
final class CategoryRuleViolation extends DomainException
{
    /** @param array<string, string|int> $params */
    private function __construct(string $message, public readonly string $key, public readonly array $params = [])
    {
        parent::__construct($message);
    }

    public static function hasChildren(string $name): self
    {
        return new self("Category \"{$name}\" has subcategories.", 'category.has_children', ['name' => $name]);
    }

    public static function inUse(string $name, int $count): self
    {
        return new self("Category \"{$name}\" is used by {$count} item(s).", 'category.in_use', ['name' => $name, 'count' => $count]);
    }

    public static function tooDeep(): self
    {
        return new self('Categories support two levels.', 'category.too_deep');
    }

    public static function parentWithChildren(): self
    {
        return new self('A category with subcategories cannot become a subcategory.', 'category.parent_with_children');
    }

    public static function selfParent(): self
    {
        return new self('A category cannot be its own parent.', 'category.self_parent');
    }
}
