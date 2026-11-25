<?php

namespace Modules\Marketplace\Domain;

use Modules\Marketplace\Domain\Exceptions\ProductRuleViolation;

/**
 * Product lifecycle (domain-rules.md §6, schema §4.1).
 *
 *   draft ─submit─▶ pending_review ─approve─▶ published ─archive─▶ archived
 *                       │    ▲                                      │
 *                    reject  └─resubmit── rejected                relist
 *                       ▼                                           ▼
 *                    rejected                                     draft
 */
enum ProductStatus: string
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
            throw ProductRuleViolation::invalidTransition($this, $next);
        }

        return $next;
    }

    /** Only a published product can be purchased (domain-model.md §5.1). */
    public function isPurchasable(): bool
    {
        return $this === self::Published;
    }

    /** Locked while under review; archived products are relisted (back to draft) before editing. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected, self::Published], true);
    }

    /** Never-published drafts can be deleted outright; anything else is archived to keep history. */
    public function isDeletable(): bool
    {
        return $this === self::Draft;
    }
}
