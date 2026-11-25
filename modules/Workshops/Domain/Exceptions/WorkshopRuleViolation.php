<?php

namespace Modules\Workshops\Domain\Exceptions;

use DomainException;
use Modules\Workshops\Domain\WorkshopStatus;

/** Workshop rules; rendered as 409 with a translated message (lang/{uk,en}/domain.php, workshop.*). */
final class WorkshopRuleViolation extends DomainException
{
    /** @param array<string, string|int> $params */
    private function __construct(string $message, public readonly string $key, public readonly array $params = [])
    {
        parent::__construct($message);
    }

    public static function makerNotApproved(): self
    {
        return new self('Only approved makers can create workshops.', 'workshop.maker_not_approved');
    }

    public static function invalidTransition(WorkshopStatus $from, WorkshopStatus $to): self
    {
        return new self("Workshop cannot move from [{$from->value}] to [{$to->value}].", 'workshop.invalid_transition', ['from' => 'workshop_'.$from->value]);
    }

    public static function notEditable(WorkshopStatus $status): self
    {
        return new self("Workshop in [{$status->value}] status cannot be edited.", 'workshop.not_editable', ['status' => 'workshop_'.$status->value]);
    }

    public static function notDeletable(): self
    {
        return new self('Only drafts can be deleted; archive the workshop instead.', 'workshop.not_deletable');
    }

    /** @param list<string> $missing */
    public static function notReady(array $missing): self
    {
        return new self(
            'Workshop is not ready for review: '.implode(', ', $missing).'.',
            'workshop.not_ready',
            ['missing' => implode(',', array_map(fn ($k) => 'workshop_req_'.$k, $missing))],
        );
    }

    public static function notEnoughSeats(int $left): self
    {
        return $left > 0
            ? new self("Only {$left} seats left.", 'workshop.seats_left', ['left' => $left])
            : new self('No seats left.', 'workshop.sold_out');
    }

    public static function capacityBelowBooked(int $booked): self
    {
        return new self("Capacity can't be lower than the {$booked} seats already booked.", 'workshop.capacity_below_booked', ['booked' => $booked]);
    }

    public static function formatLocked(): self
    {
        return new self("The format can't change once seats are booked.", 'workshop.format_locked');
    }

    public static function invalidSession(): self
    {
        return new self('A session must end after it starts and last at most 24 hours.', 'workshop.invalid_session');
    }

    public static function lastSession(): self
    {
        return new self('A workshop with bookings needs at least one session.', 'workshop.last_session');
    }
}
