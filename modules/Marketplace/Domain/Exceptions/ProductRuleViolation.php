<?php

namespace Modules\Marketplace\Domain\Exceptions;

use DomainException;
use Modules\Marketplace\Domain\ProductStatus;

/** Product rules; rendered as 409 with a translated message (lang/{uk,en}/domain.php, product.*). */
final class ProductRuleViolation extends DomainException
{
    /** @param array<string, string|int> $params */
    private function __construct(string $message, public readonly string $key, public readonly array $params = [])
    {
        parent::__construct($message);
    }

    public static function makerNotApproved(): self
    {
        return new self('Only approved makers can create products.', 'product.maker_not_approved');
    }

    public static function invalidTransition(ProductStatus $from, ProductStatus $to): self
    {
        return new self("Product cannot move from [{$from->value}] to [{$to->value}].", 'product.invalid_transition', ['from' => 'product_'.$from->value]);
    }

    public static function notEditable(ProductStatus $status): self
    {
        return new self("Product in [{$status->value}] status cannot be edited.", 'product.not_editable', ['status' => 'product_'.$status->value]);
    }

    public static function notDeletable(): self
    {
        return new self('Only drafts can be deleted; archive the product instead.', 'product.not_deletable');
    }

    /** @param list<string> $missing requirement keys from ProductReadiness */
    public static function notReady(array $missing): self
    {
        return new self(
            'Product is not ready for review: '.implode(', ', $missing).'.',
            'product.not_ready',
            ['missing' => implode(',', array_map(fn ($k) => 'req_'.$k, $missing))],
        );
    }

    public static function insufficientStock(string $variantName, int $available, int $requested): self
    {
        return new self(
            "Not enough stock for [{$variantName}]: {$available} available, {$requested} requested.",
            'product.insufficient_stock',
            ['variant' => $variantName, 'available' => $available],
        );
    }

    public static function lastVariant(): self
    {
        return new self('A physical product must keep at least one variant.', 'product.last_variant');
    }

    public static function typeLocked(): self
    {
        return new self('Product type cannot be changed after the product was submitted.', 'product.type_locked');
    }
}
