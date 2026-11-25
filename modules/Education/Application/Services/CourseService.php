<?php

namespace Modules\Education\Application\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Education\Domain\AccessType;
use Modules\Education\Domain\CourseReadiness;
use Modules\Education\Domain\CourseStatus;
use Modules\Education\Domain\Exceptions\CourseRuleViolation;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Education\Infrastructure\Persistence\Lesson;
use Modules\Identity\Application\Contracts\MakerSummary;

/** Course details and lifecycle for Makers and moderators. The curriculum lives in CurriculumService. */
final class CourseService
{
    /**
     * @param  array{name: string, description?: ?string, price_amount_minor?: int, currency?: string, access_type?: string, access_days?: ?int, certificate_enabled?: bool, category_uuid?: ?string, content_locale?: string, translations?: ?array}  $data
     */
    public function create(MakerSummary $maker, array $data): Course
    {
        if (! $maker->isApproved) {
            throw CourseRuleViolation::makerNotApproved();
        }

        return DB::transaction(function () use ($maker, $data) {
            $course = new Course(['maker_profile_uuid' => $maker->uuid, 'slug' => $this->uniqueSlug($data['name'])]);
            $course->status = CourseStatus::Draft;
            $this->apply($course, $data);
            $course->save();
            $this->syncCategory($course, $data);

            return $course->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Course $course, array $data): Course
    {
        $this->assertEditable($course);

        return DB::transaction(function () use ($course, $data) {
            // The slug follows the name until the course has been published once
            if ($course->published_at === null && isset($data['name']) && $course->name !== $data['name']) {
                $course->slug = $this->uniqueSlug($data['name'], ignore: $course->uuid);
            }
            $this->apply($course, $data);
            $course->save();
            $this->syncCategory($course, $data);

            return $course->refresh();
        });
    }

    public function setCover(Course $course, UploadedFile $file): Course
    {
        $this->assertEditable($course);
        $old = $course->cover_path;
        $course->cover_path = $file->store("course-covers/{$course->uuid}", 'public');
        $course->save();
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return $course;
    }

    /** @return list<string> */
    public function missingForReview(Course $course): array
    {
        $lessons = Lesson::query()->whereIn('course_module_uuid', $course->modules()->select('uuid'));

        return CourseReadiness::missing([
            'name' => $course->name,
            'description' => $course->description,
            'price_minor' => $course->price_amount_minor,
            'access_type' => $course->access_type,
            'access_days' => $course->access_days,
            'lessons' => (clone $lessons)->count(),
            'lessons_without_materials' => (clone $lessons)->whereDoesntHave('materials')->count(),
            'required_lessons' => (clone $lessons)->where('is_required', true)->count(),
        ]);
    }

    public function submitForReview(Course $course): Course
    {
        $next = $course->status->transitionTo(CourseStatus::PendingReview);
        if (($missing = $this->missingForReview($course)) !== []) {
            throw CourseRuleViolation::notReady($missing);
        }

        return $this->setStatus($course, $next, null);
    }

    public function approve(Course $course): Course
    {
        $course->status = $course->status->transitionTo(CourseStatus::Published);
        $course->review_note = null;
        $course->published_at ??= now();
        $course->save();

        return $course;
    }

    public function reject(Course $course, string $note): Course
    {
        return $this->setStatus($course, $course->status->transitionTo(CourseStatus::Rejected), $note);
    }

    /** Archived courses can't be bought; existing students keep their access (domain-rules.md §13). */
    public function archive(Course $course, ?string $note = null): Course
    {
        return $this->setStatus($course, $course->status->transitionTo(CourseStatus::Archived), $note);
    }

    public function relist(Course $course): Course
    {
        return $this->setStatus($course, $course->status->transitionTo(CourseStatus::Draft), null);
    }

    public function delete(Course $course): void
    {
        if (! $course->status->isDeletable()) {
            throw CourseRuleViolation::notDeletable();
        }

        DB::transaction(function () use ($course) {
            $course->load('modules.lessons.materials');
            foreach ($course->modules as $module) {
                foreach ($module->lessons as $lesson) {
                    foreach ($lesson->materials as $material) {
                        if ($material->storage_path) {
                            Storage::disk($material->storage_disk)->delete($material->storage_path);
                        }
                    }
                }
            }
            if ($course->cover_path) {
                Storage::disk('public')->delete($course->cover_path);
            }
            $course->categories()->detach();
            $course->forceDelete();
        });
    }

    public function assertEditable(Course $course): void
    {
        if (! $course->status->isEditable()) {
            throw CourseRuleViolation::notEditable($course->status);
        }
    }

    /** @param array<string, mixed> $data */
    private function apply(Course $course, array $data): void
    {
        $course->fill(array_intersect_key($data, array_flip([
            'name', 'description', 'price_amount_minor', 'currency', 'access_type', 'access_days', 'certificate_enabled', 'content_locale',
        ])));
        if ($course->access_type === AccessType::Lifetime) {
            $course->access_days = null;
        }
        if (array_key_exists('translations', $data)) {
            $course->translations = $course->cleanTranslations($data['translations'] ?? []) ?: null;
        }
    }

    /** @param array<string, mixed> $data */
    private function syncCategory(Course $course, array $data): void
    {
        if (array_key_exists('category_uuid', $data)) {
            $course->categories()->sync($data['category_uuid'] ? [$data['category_uuid'] => ['created_at' => now()]] : []);
        }
    }

    private function setStatus(Course $course, CourseStatus $status, ?string $note): Course
    {
        $course->status = $status;
        $course->review_note = $note;
        $course->save();

        return $course;
    }

    private function uniqueSlug(string $name, ?string $ignore = null): string
    {
        $base = Str::slug($name) ?: 'course';
        $slug = $base;
        $i = 2;
        while (Course::withTrashed()->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
