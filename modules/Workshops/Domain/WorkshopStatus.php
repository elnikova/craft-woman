<?php

namespace Modules\Workshops\Domain;

use Modules\Workshops\Domain\Exceptions\WorkshopRuleViolation;

/**
 * Workshop lifecycle — the moderation flow of products and courses, plus cancellation:
 *
 *   draft ─submit─▶ pending_review ─approve─▶ published ─archive─▶ archived ─relist─▶ draft
 *                       │    ▲                    │                   │
 *                    reject  └─resubmit─ rejected cancel ─────────────┴─cancel─▶ cancelled (final)
 */
enum WorkshopStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Archived = 'archived';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview],
            self::PendingReview => [self::Published, self::Rejected],
            self::Published => [self::Archived, self::Cancelled],
            self::Rejected => [self::PendingReview],
            self::Archived => [self::Draft, self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function transitionTo(self $next): self
    {
        if (! in_array($next, $this->allowedTransitions(), true)) {
            throw WorkshopRuleViolation::invalidTransition($this, $next);
        }

        return $next;
    }

    /** Locked while in review. Published workshops stay editable so makers can fix details for participants. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected, self::Published], true);
    }

    public function isDeletable(): bool
    {
        return $this === self::Draft;
    }
}
