<?php

use Modules\Workshops\Domain\Exceptions\WorkshopRuleViolation;
use Modules\Workshops\Domain\Seats;
use Modules\Workshops\Domain\WorkshopFormat;
use Modules\Workshops\Domain\WorkshopReadiness;
use Modules\Workshops\Domain\WorkshopStatus;

function workshopFacts(array $overrides = []): array
{
    return [
        'name' => 'Гончарний круг', 'description' => str_repeat('Опис майстер-класу. ', 3), 'price_minor' => 4500, 'capacity' => 6,
        'format' => WorkshopFormat::Offline, 'country' => 'UA', 'city' => 'Опішня', 'address' => 'вул. Гончарна, 12',
        'sessions' => 1, 'first_start_in_future' => true, 'invalid_sessions' => 0, ...$overrides,
    ];
}

test('a complete workshop is ready for review', function () {
    expect(WorkshopReadiness::missing(workshopFacts()))->toBe([]);
});

test('readiness lists what is missing', function () {
    expect(WorkshopReadiness::missing(workshopFacts(['capacity' => 0, 'address' => ' ', 'sessions' => 0])))
        ->toBe(['capacity', 'location', 'sessions']);
    expect(WorkshopReadiness::missing(workshopFacts(['first_start_in_future' => false, 'invalid_sessions' => 1])))
        ->toBe(['future_sessions', 'session_times']);
});

test('online workshops need no address and the meeting link can come later', function () {
    expect(WorkshopReadiness::missing(workshopFacts(['format' => WorkshopFormat::Online, 'country' => null, 'city' => null, 'address' => null])))->toBe([]);
});

test('seats are never oversold', function () {
    expect(Seats::left(6, 4))->toBe(2)->and(Seats::left(6, 9))->toBe(0);
    Seats::assertAvailable(6, 4, 2);
    expect(fn () => Seats::assertAvailable(6, 4, 3))->toThrow(WorkshopRuleViolation::class, 'Only 2 seats left.');
    expect(fn () => Seats::assertAvailable(6, 6, 1))->toThrow(WorkshopRuleViolation::class, 'No seats left.');
});

test('cancelled is final; published workshops can be cancelled', function () {
    expect(WorkshopStatus::Published->transitionTo(WorkshopStatus::Cancelled))->toBe(WorkshopStatus::Cancelled);
    expect(fn () => WorkshopStatus::Cancelled->transitionTo(WorkshopStatus::Draft))->toThrow(WorkshopRuleViolation::class);
    expect(fn () => WorkshopStatus::Draft->transitionTo(WorkshopStatus::Cancelled))->toThrow(WorkshopRuleViolation::class);
});
