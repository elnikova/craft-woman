<?php

namespace Modules\Education\Application\Contracts;

/** Education's read interface for Orders (module-architecture.md). */
interface CourseCatalog
{
    public function line(string $courseUuid): ?CourseLine;

    /**
     * @param  list<string>  $courseUuids
     * @return array<string, CourseLine> keyed by course uuid
     */
    public function lines(array $courseUuids): array;

    /** True when the user already has open lifetime access, so buying again makes no sense. */
    public function hasLifetimeAccess(string $userUuid, string $courseUuid): bool;
}
