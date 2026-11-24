<?php

namespace Modules\Orders\Domain;

/**
 * Overall order state (schema §7.3). Derived from the groups' states, so the order never
 * contradicts what each Maker is doing (domain-rules.md §23: Makers fulfil independently).
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Processing = 'processing';
    case PartiallyShipped = 'partially_shipped';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @param list<GroupStatus> $groups */
    public static function derive(array $groups): self
    {
        $active = array_values(array_filter($groups, fn (GroupStatus $g) => $g !== GroupStatus::Cancelled));

        if ($active === []) {
            return self::Cancelled;
        }
        if (in_array(GroupStatus::PendingPayment, $active, true)) {
            return self::PendingPayment;
        }

        $finished = count(array_filter($active, fn (GroupStatus $g) => $g->isFinished()));
        $left = count(array_filter($active, fn (GroupStatus $g) => $g->hasLeftTheMaker()));

        return match (true) {
            $finished === count($active) => self::Completed,
            $left === count($active) => self::Shipped,
            $left > 0 => self::PartiallyShipped,
            default => self::Processing,
        };
    }
}
