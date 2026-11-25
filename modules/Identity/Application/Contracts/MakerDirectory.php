<?php

namespace Modules\Identity\Application\Contracts;

/**
 * Identity's public read interface for other modules (module-architecture.md §2.5:
 * no direct cross-module Eloquent access).
 */
interface MakerDirectory
{
    /** The Maker profile of a user, whatever its status. */
    public function forUser(string $userUuid): ?MakerSummary;

    public function findBySlug(string $slug): ?MakerSummary;

    /**
     * Subquery selecting UUIDs of approved Makers, for `whereIn('maker_profile_uuid', …)`.
     * Lets other modules hide content of suspended Makers without touching Identity tables.
     */
    public function approvedMakerUuidsQuery(): \Illuminate\Database\Query\Builder;

    /**
     * @param  list<string>  $makerUuids
     * @return array<string, MakerSummary> keyed by maker uuid
     */
    public function summaries(array $makerUuids): array;
}
