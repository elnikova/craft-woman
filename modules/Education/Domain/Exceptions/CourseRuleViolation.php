<?php

namespace Modules\Education\Domain\Exceptions;

use DomainException;
use Modules\Education\Domain\CourseStatus;

/** Course rules; rendered as 409 with a translated message (lang/{uk,en}/domain.php, course.*). */
final class CourseRuleViolation extends DomainException
{
    /** @param array<string, string|int> $params */
    private function __construct(string $message, public readonly string $key, public readonly array $params = [])
    {
        parent::__construct($message);
    }

    public static function makerNotApproved(): self
    {
        return new self('Only approved makers can create courses.', 'course.maker_not_approved');
    }

    public static function invalidTransition(CourseStatus $from, CourseStatus $to): self
    {
        return new self("Course cannot move from [{$from->value}] to [{$to->value}].", 'course.invalid_transition', ['from' => 'product_'.$from->value]);
    }

    public static function notEditable(CourseStatus $status): self
    {
        return new self("Course in [{$status->value}] status cannot be edited.", 'course.not_editable', ['status' => 'product_'.$status->value]);
    }

    public static function notDeletable(): self
    {
        return new self('Only drafts can be deleted; archive the course instead.', 'course.not_deletable');
    }

    /** @param list<string> $missing */
    public static function notReady(array $missing): self
    {
        return new self(
            'Course is not ready for review: '.implode(', ', $missing).'.',
            'course.not_ready',
            ['missing' => implode(',', array_map(fn ($k) => 'course_req_'.$k, $missing))],
        );
    }

    public static function invalidVideo(): self
    {
        return new self('Only YouTube and Vimeo links are supported.', 'course.invalid_video');
    }

    public static function noAccess(): self
    {
        return new self('No active access to this course.', 'course.no_access');
    }

    public static function accessExpired(): self
    {
        return new self('Course access has expired.', 'course.access_expired');
    }

    public static function notCompleted(): self
    {
        return new self('Complete all required lessons first.', 'course.not_completed');
    }

    public static function certificatesDisabled(): self
    {
        return new self('This course does not issue certificates.', 'course.certificates_disabled');
    }

    public static function alreadyEnrolled(): self
    {
        return new self('You already have lifetime access to this course.', 'course.already_enrolled');
    }
}
