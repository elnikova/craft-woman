<?php

namespace Modules\Education\Application\Listeners;

use Modules\Education\Application\Services\EnrollmentService;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Orders\Application\Events\OrderPaid;

/** Enrollment is created only after successful payment (schema §5.5). */
final class GrantCourseAccess
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function handle(OrderPaid $event): void
    {
        if ($event->userUuid === null) {
            return;
        }
        foreach ($event->courseItems as ['order_item_uuid' => $itemUuid, 'course_uuid' => $courseUuid]) {
            $course = Course::withTrashed()->find($courseUuid);
            if ($course) {
                $this->enrollments->grant($event->userUuid, $course, $itemUuid);
            }
        }
    }
}
