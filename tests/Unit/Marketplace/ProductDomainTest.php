<?php

use Modules\Marketplace\Domain\Currency;
use Modules\Marketplace\Domain\Exceptions\ProductRuleViolation;
use Modules\Marketplace\Domain\ProductReadiness;
use Modules\Marketplace\Domain\ProductStatus;
use Modules\Marketplace\Domain\ProductType;
use Modules\Marketplace\Domain\ValueObjects\Money;

uses()->group('products');

it('follows the documented product lifecycle', function (ProductStatus $from, ProductStatus $to) {
    expect($from->transitionTo($to))->toBe($to);
})->with([
    [ProductStatus::Draft, ProductStatus::PendingReview],
    [ProductStatus::PendingReview, ProductStatus::Published],
    [ProductStatus::PendingReview, ProductStatus::Rejected],
    [ProductStatus::Rejected, ProductStatus::PendingReview],
    [ProductStatus::Published, ProductStatus::Archived],
    [ProductStatus::Archived, ProductStatus::Draft],
]);

it('forbids skipping moderation', function (ProductStatus $from, ProductStatus $to) {
    $from->transitionTo($to);
})->with([
    [ProductStatus::Draft, ProductStatus::Published],
    [ProductStatus::Rejected, ProductStatus::Published],
    [ProductStatus::Archived, ProductStatus::Published],
])->throws(ProductRuleViolation::class);

it('makes only published products purchasable', function () {
    expect(collect(ProductStatus::cases())->filter->isPurchasable()->values()->all())->toBe([ProductStatus::Published]);
});

it('never stores negative money and multiplies in minor units', function () {
    expect((new Money(1250, Currency::UAH))->multiply(3)->amountMinor)->toBe(3750);
    new Money(-1, Currency::EUR);
})->throws(InvalidArgumentException::class);

it('lists what a product still needs before review', function () {
    expect(ProductReadiness::missing(ProductType::Digital, 'Схема', 'короткий', 0, false, 0, 0, 0))
        ->toBe(['description', 'price', 'category', 'image', 'file']);

    expect(ProductReadiness::missing(ProductType::Physical, 'Чашка «Ранок»', str_repeat('глина ', 5), 2800, true, 1, 1, 0))
        ->toBe([]);

    expect(ProductReadiness::missing(ProductType::Physical, 'Чашка', str_repeat('глина ', 5), 2800, true, 1, 0, 0))
        ->toBe(['variant']);
});
