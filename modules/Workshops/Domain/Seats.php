<?php

namespace Modules\Workshops\Domain;

use Modules\Workshops\Domain\Exceptions\WorkshopRuleViolation;

/** Seat arithmetic, kept pure for tests. Booking is for the whole workshop, so capacity is one number. */
final class Seats
{
    public static function left(int $capacity, int $taken): int
    {
        return max(0, $capacity - $taken);
    }

    public static function assertAvailable(int $capacity, int $taken, int $requested): void
    {
        if ($requested < 1) {
            throw WorkshopRuleViolation::notEnoughSeats(self::left($capacity, $taken));
        }
        if ($requested > self::left($capacity, $taken)) {
            throw WorkshopRuleViolation::notEnoughSeats(self::left($capacity, $taken));
        }
    }
}
