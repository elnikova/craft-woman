<?php

namespace Modules\Orders\Domain;

/**
 * Which payment methods an order may use (domain-rules.md §25–27, decisions of 2026-11-24):
 * - online payment is always available;
 * - payment on delivery only if EVERY Maker in the order allows it,
 *   nothing ships internationally (§26: international → full prepayment),
 *   and there are no digital items (they are delivered only after payment, §10).
 */
final class PaymentPolicy
{
    /**
     * @param  list<CheckoutGroupFacts>  $groups
     * @return array<string, list<string>> method => reasons it is unavailable (empty = available)
     */
    public static function evaluate(array $groups): array
    {
        $codReasons = [];

        foreach ($groups as $g) {
            if ($g->hasDigital) {
                $codReasons[] = 'cod_digital';
            }
            if ($g->hasPhysical && ! $g->makerAllowsCod) {
                $codReasons[] = 'cod_maker';
            }
            if ($g->hasPhysical && $g->isInternational) {
                $codReasons[] = 'cod_international';
            }
        }

        return [
            PaymentMethod::Online->value => [],
            PaymentMethod::CashOnDelivery->value => array_values(array_unique($codReasons)),
        ];
    }

    /** @param list<CheckoutGroupFacts> $groups */
    public static function allows(PaymentMethod $method, array $groups): bool
    {
        return self::evaluate($groups)[$method->value] === [];
    }
}
