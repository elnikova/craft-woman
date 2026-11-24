<?php

namespace Modules\Identity\Domain;

use Modules\Identity\Domain\Exceptions\InvalidMakerStatusTransition;

/**
 * Maker approval lifecycle (domain-rules.md §3, postgresql-schema.md §3.4).
 *
 *   draft ──submit──▶ pending_review ──approve──▶ approved ──suspend──▶ suspended
 *     ▲                     │                        ▲                      │
 *     │                  reject                      └──────reinstate───────┘
 *     │                     ▼
 *     └──(edit)────── rejected ──submit──▶ pending_review
 *
 * Pure PHP: no Laravel dependencies.
 */
enum MakerStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview],
            self::PendingReview => [self::Approved, self::Rejected],
            self::Approved => [self::Suspended],
            self::Rejected => [self::PendingReview],
            self::Suspended => [self::Approved],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /** @throws InvalidMakerStatusTransition */
    public function transitionTo(self $next): self
    {
        if (! $this->canTransitionTo($next)) {
            throw InvalidMakerStatusTransition::between($this, $next);
        }

        return $next;
    }

    /** Only an approved Maker has full Maker capabilities and a public profile. */
    public function isActive(): bool
    {
        return $this === self::Approved;
    }

    /** The Maker may edit the profile only while it is not under review or suspended. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected, self::Approved], true);
    }
}
