<?php

namespace Modules\Education\Domain;

use Modules\Education\Domain\Exceptions\CourseRuleViolation;

/**
 * Course lifecycle (domain-rules.md §12) — the same moderation flow as products:
 *
 *   draft ─submit─▶ pending_review ─approve─▶ published ─archive─▶ archived
 *                       │    ▲                                      │
 *                    reject  └─resubmit── rejected                relist
 */
enum CourseStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Archived = 'archived';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview],
            self::PendingReview => [self::Published, self::Rejected],
            self::Published => [self::Archived],
            self::Rejected => [self::PendingReview],
            self::Archived => [self::Draft],
        };
    }

    public function transitionTo(self $next): self
    {
        if (! in_array($next, $this->allowedTransitions(), true)) {
            throw CourseRuleViolation::invalidTransition($this, $next);
        }

        return $next;
    }

    /** Only published courses can be bought (domain-rules.md §12). */
    public function isPurchasable(): bool
    {
        return $this === self::Published;
    }

    /** Locked while in review. Published courses stay editable so makers can fix lessons for current students. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected, self::Published], true);
    }

    public function isDeletable(): bool
    {
        return $this === self::Draft;
    }
}
