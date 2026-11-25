<?php

namespace Modules\Orders\Domain\Exceptions;

use DomainException;
use Modules\Orders\Domain\GroupStatus;

/** Cart / checkout / order rules; rendered as 409 with a translated message (domain.order.*). */
final class OrderRuleViolation extends DomainException
{
    /** @param array<string, string|int> $params */
    private function __construct(string $message, public readonly string $key, public readonly array $params = [])
    {
        parent::__construct($message);
    }

    public static function currencyMismatch(string $cart, string $product): self
    {
        return new self("Cart is in {$cart}, product is in {$product}.", 'order.currency_mismatch', ['cart' => $cart, 'product' => $product]);
    }

    public static function notPurchasable(string $name): self
    {
        return new self("Product [{$name}] is not available for purchase.", 'order.not_purchasable', ['name' => $name]);
    }

    public static function insufficientStock(string $name, int $available): self
    {
        return new self("Only {$available} of [{$name}] available.", 'order.insufficient_stock', ['name' => $name, 'available' => $available]);
    }

    public static function emptyCart(): self
    {
        return new self('Cart is empty.', 'order.empty_cart');
    }

    public static function cartChanged(): self
    {
        return new self('Cart changed since it was reviewed.', 'order.cart_changed');
    }

    /** @param list<string> $reasons */
    public static function paymentMethodUnavailable(array $reasons): self
    {
        return new self('Payment on delivery is not available: '.implode(',', $reasons), 'order.cod_unavailable', ['reasons' => implode(',', $reasons)]);
    }

    public static function noInternationalShipping(string $maker): self
    {
        return new self("[{$maker}] does not ship abroad.", 'order.no_international_shipping', ['maker' => $maker]);
    }

    public static function deliveryCurrency(string $maker): self
    {
        return new self("[{$maker}] delivery fee is in another currency.", 'order.delivery_currency', ['maker' => $maker]);
    }

    public static function invalidGroupTransition(GroupStatus $from, GroupStatus $to): self
    {
        return new self("Order part cannot move from [{$from->value}] to [{$to->value}].", 'order.invalid_transition', ['from' => 'group_'.$from->value]);
    }

    public static function notCancellable(): self
    {
        return new self('Order can no longer be cancelled.', 'order.not_cancellable');
    }

    public static function notPaid(): self
    {
        return new self('Order is not paid yet.', 'order.not_paid');
    }
}
