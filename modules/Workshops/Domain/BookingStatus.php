<?php

namespace Modules\Workshops\Domain;

/**
 * pending   — seats held by a placed but unpaid order;
 * confirmed — paid;
 * cancelled — order cancelled or the maker cancelled the workshop (seats released).
 *
 * "Completed" (schema §6.3) is derived from the last session's end instead of being stored.
 */
enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /** Statuses that occupy seats */
    public static function holding(): array
    {
        return [self::Pending, self::Confirmed];
    }
}
