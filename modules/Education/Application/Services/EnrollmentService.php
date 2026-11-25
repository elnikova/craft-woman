<?php

namespace Modules\Education\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Education\Domain\AccessType;
use Modules\Education\Domain\EnrollmentStatus;
use Modules\Education\Domain\Exceptions\CourseRuleViolation;
use Modules\Education\Infrastructure\Persistence\Certificate;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Education\Infrastructure\Persistence\Enrollment;
use Modules\Education\Infrastructure\Persistence\Lesson;
use Modules\Identity\Application\Contracts\MakerDirectory;

/**
 * Access, progress and certificates (domain-rules.md §13–15).
 * Completion rule (decision 2026-11-25): every required lesson is marked as completed.
 */
final class EnrollmentService
{
    public function __construct(private readonly MakerDirectory $makers) {}

    /**
     * Grant access after a paid order. Buying again extends limited access from today or
     * from the current expiry, whichever is later; progress is kept.
     */
    public function grant(string $userUuid, Course $course, ?string $orderItemUuid): Enrollment
    {
        return DB::transaction(function () use ($userUuid, $course, $orderItemUuid) {
            $enrollment = Enrollment::query()->where('user_uuid', $userUuid)->where('course_uuid', $course->uuid)->lockForUpdate()->first();

            $from = $enrollment?->expires_at && $enrollment->expires_at->isFuture() ? $enrollment->expires_at : now();
            $expires = $course->access_type === AccessType::Limited ? $from->copy()->addDays((int) $course->access_days) : null;

            $attributes = [
                'order_item_uuid' => $orderItemUuid,
                'expires_at' => $expires,
                'status' => $enrollment?->completed_at ? EnrollmentStatus::Completed : EnrollmentStatus::Active,
            ];

            if ($enrollment) {
                $enrollment->update($attributes);

                return $enrollment;
            }

            return Enrollment::create([...$attributes, 'user_uuid' => $userUuid, 'course_uuid' => $course->uuid, 'starts_at' => now()]);
        });
    }

    public function find(User $user, Course $course): ?Enrollment
    {
        return Enrollment::query()->where('user_uuid', $user->uuid)->where('course_uuid', $course->uuid)->first();
    }

    /** Whether the user can open this lesson: students with access, free previews, the owner and admins. */
    public function canView(?User $user, Course $course, Lesson $lesson): bool
    {
        if ($lesson->is_preview) {
            return true;
        }
        if (! $user) {
            return false;
        }
        if ($user->isAdmin() || $this->makers->forUser($user->uuid)?->uuid === $course->maker_profile_uuid) {
            return true;
        }

        return (bool) $this->find($user, $course)?->hasAccess();
    }

    public function requireAccess(User $user, Course $course): Enrollment
    {
        $enrollment = $this->find($user, $course) ?? throw CourseRuleViolation::noAccess();
        if ($enrollment->status === EnrollmentStatus::Cancelled) {
            throw CourseRuleViolation::noAccess();
        }
        if ($enrollment->isExpired()) {
            throw CourseRuleViolation::accessExpired();
        }

        return $enrollment;
    }

    public function complete(Enrollment $enrollment, Lesson $lesson): Enrollment
    {
        $enrollment->progress()->updateOrCreate(['lesson_uuid' => $lesson->uuid], ['completed_at' => now()]);

        return $this->refreshCompletion($enrollment);
    }

    public function uncomplete(Enrollment $enrollment, Lesson $lesson): Enrollment
    {
        $enrollment->progress()->where('lesson_uuid', $lesson->uuid)->delete();

        return $this->refreshCompletion($enrollment);
    }

    /**
     * @return array{completed: int, total: int, required_completed: int, required_total: int, percent: int, completed_lessons: list<string>}
     */
    public function progress(Enrollment $enrollment): array
    {
        $lessons = $this->lessons($enrollment->course);
        $done = $enrollment->progress()->whereNotNull('completed_at')->pluck('lesson_uuid')->all();
        $doneSet = array_flip($done);
        $required = $lessons->where('is_required', true);

        $completed = $lessons->filter(fn (Lesson $l) => isset($doneSet[$l->uuid]))->count();

        return [
            'completed' => $completed,
            'total' => $lessons->count(),
            'required_completed' => $required->filter(fn (Lesson $l) => isset($doneSet[$l->uuid]))->count(),
            'required_total' => $required->count(),
            'percent' => $lessons->count() ? (int) floor($completed * 100 / $lessons->count()) : 0,
            'completed_lessons' => array_values(array_intersect($done, $lessons->pluck('uuid')->all())),
        ];
    }

    /** Issue (or return the existing) certificate once the course is completed. */
    public function certificate(Enrollment $enrollment): Certificate
    {
        $course = $enrollment->course;
        if (! $course->certificate_enabled) {
            throw CourseRuleViolation::certificatesDisabled();
        }
        if ($enrollment->completed_at === null) {
            throw CourseRuleViolation::notCompleted();
        }

        return $enrollment->certificate ?? $enrollment->certificate()->create([
            'certificate_number' => $this->certificateNumber(),
            'holder_name' => $enrollment->user->name,
            'course_name' => $course->name,
            'maker_name' => $this->makers->summaries([$course->maker_profile_uuid])[$course->maker_profile_uuid]?->displayName ?? '—',
            'issued_at' => now(),
        ]);
    }

    private function refreshCompletion(Enrollment $enrollment): Enrollment
    {
        $p = $this->progress($enrollment);
        $complete = $p['required_total'] > 0 && $p['required_completed'] === $p['required_total'];

        // Completion is recorded once; later curriculum changes don't take a certificate away
        if ($complete && $enrollment->completed_at === null) {
            $enrollment->update(['completed_at' => now(), 'status' => EnrollmentStatus::Completed]);
        }

        return $enrollment->refresh();
    }

    /** @return \Illuminate\Support\Collection<int, Lesson> */
    private function lessons(Course $course)
    {
        return Lesson::query()->whereIn('course_module_uuid', $course->modules()->select('uuid'))->get(['uuid', 'is_required']);
    }

    private function certificateNumber(): string
    {
        do {
            $number = 'CWC-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (Certificate::query()->where('certificate_number', $number)->exists());

        return $number;
    }
}
