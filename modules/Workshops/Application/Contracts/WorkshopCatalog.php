<?php

namespace Modules\Workshops\Application\Contracts;

/** Workshops' interface for Orders: read lines, hold seats at checkout, confirm or release them. */
interface WorkshopCatalog
{
    public function line(string $workshopUuid): ?WorkshopLine;

    /**
     * @param  list<string>  $workshopUuids
     * @return array<string, WorkshopLine> keyed by workshop uuid
     */
    public function lines(array $workshopUuids): array;

    /**
     * Hold seats for a placed order line. Must run inside the checkout transaction;
     * throws WorkshopRuleViolation when there aren't enough seats.
     */
    public function reserve(string $workshopUuid, string $userUuid, string $orderItemUuid, int $seats): void;

    /** @param list<string> $orderItemUuids */
    public function release(array $orderItemUuids, string $reason): void;
}
