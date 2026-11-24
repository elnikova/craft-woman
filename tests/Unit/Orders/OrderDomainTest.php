<?php

use Modules\Orders\Domain\CheckoutGroupFacts;
use Modules\Orders\Domain\GroupStatus as G;
use Modules\Orders\Domain\OrderStatus as O;
use Modules\Orders\Domain\PaymentMethod;
use Modules\Orders\Domain\PaymentPolicy;

uses()->group('orders');

function facts(bool $physical = true, bool $digital = false, bool $international = false, bool $cod = true): CheckoutGroupFacts
{
    return new CheckoutGroupFacts('m', $physical, $digital, $international, $cod, true);
}

it('derives the overall order status from the makers\' parts', function (array $groups, O $expected) {
    expect(O::derive($groups))->toBe($expected);
})->with([
    'one unpaid part' => [[G::PendingPayment, G::AwaitingShipment], O::PendingPayment],
    'all preparing' => [[G::AwaitingShipment, G::AwaitingShipment], O::Processing],
    'one shipped' => [[G::Shipped, G::AwaitingShipment], O::PartiallyShipped],
    'all on the way' => [[G::Shipped, G::Delivered], O::Shipped],
    'all done' => [[G::Delivered, G::Completed], O::Completed],
    'cancelled part ignored' => [[G::Delivered, G::Cancelled], O::Completed],
    'everything cancelled' => [[G::Cancelled, G::Cancelled], O::Cancelled],
]);

it('allows payment on delivery only when every maker allows it', function () {
    expect(PaymentPolicy::allows(PaymentMethod::CashOnDelivery, [facts(), facts()]))->toBeTrue();
    expect(PaymentPolicy::evaluate([facts(), facts(cod: false)])['cash_on_delivery'])->toBe(['cod_maker']);
});

it('requires prepayment for international delivery even if the maker allows COD', function () {
    expect(PaymentPolicy::evaluate([facts(international: true)])['cash_on_delivery'])->toBe(['cod_international']);
});

it('requires prepayment for digital items and always allows online payment', function () {
    $eval = PaymentPolicy::evaluate([facts(physical: false, digital: true)]);
    expect($eval['cash_on_delivery'])->toBe(['cod_digital'])->and($eval['online'])->toBe([]);
});

it('does not let a shipped part be cancelled', function () {
    G::Shipped->transitionTo(G::Cancelled);
})->throws(Modules\Orders\Domain\Exceptions\OrderRuleViolation::class);
