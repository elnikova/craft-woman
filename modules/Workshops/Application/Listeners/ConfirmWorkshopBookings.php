<?php

namespace Modules\Workshops\Application\Listeners;

use Modules\Orders\Application\Events\OrderPaid;
use Modules\Workshops\Application\Services\BookingService;

/** Seats held at checkout become confirmed bookings once the order is paid. */
final class ConfirmWorkshopBookings
{
    public function __construct(private readonly BookingService $bookings) {}

    public function handle(OrderPaid $event): void
    {
        $this->bookings->confirm($event->workshopItems);
    }
}
