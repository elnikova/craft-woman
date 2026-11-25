<?php

namespace Modules\Orders\Application\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched once, when an order becomes paid. Other modules react (course access, workshop bookings). */
final class OrderPaid
{
    use Dispatchable;

    /**
     * @param  list<array{order_item_uuid: string, course_uuid: string}>  $courseItems
     * @param  list<string>  $workshopItems  order item uuids whose held seats become confirmed
     */
    public function __construct(
        public readonly string $orderUuid,
        public readonly ?string $userUuid,
        public readonly array $courseItems,
        public readonly array $workshopItems = [],
    ) {}
}
