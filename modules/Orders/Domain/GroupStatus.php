<?php

namespace Modules\Orders\Domain;

use Modules\Orders\Domain\Exceptions\OrderRuleViolation;

/**
 * Seller-specific fulfilment state of one Maker's part of an order (domain-rules.md §23).
 *
 *   pending_payment ─paid─▶ awaiting_shipment ─ship─▶ shipped ─deliver─▶ delivered
 *         │                        │                                (physical)
 *         │                        └─cancel─▶ cancelled
 *         ├─paid (digital only)─▶ completed
 *         └─cancel─▶ cancelled
 */
enum GroupStatus: string
{
    case PendingPayment = 'pending_payment';
    case AwaitingShipment = 'awaiting_shipment';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::AwaitingShipment, self::Completed, self::Cancelled],
            self::AwaitingShipment => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Completed, self::Cancelled => [],
        };
    }

    public function transitionTo(self $next): self
    {
        if (! in_array($next, $this->allowedTransitions(), true)) {
            throw OrderRuleViolation::invalidGroupTransition($this, $next);
        }

        return $next;
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Delivered, self::Completed], true);
    }

    public function hasLeftTheMaker(): bool
    {
        return in_array($this, [self::Shipped, self::Delivered, self::Completed], true);
    }
}
