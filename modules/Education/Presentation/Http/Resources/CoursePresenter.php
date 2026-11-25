<?php

namespace Modules\Education\Presentation\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Education\Domain\MaterialType;
use Modules\Education\Domain\VideoUrl;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Education\Infrastructure\Persistence\CourseModule;
use Modules\Education\Infrastructure\Persistence\Lesson;
use Modules\Education\Infrastructure\Persistence\LessonMaterial;
use Modules\Identity\Application\Contracts\MakerDirectory;

/**
 * Shapes courses for the API. Three views:
 *  - card (catalog lists), detail (public course page with the outline),
 *  - editor (maker/admin: source text, translations, full curriculum, readiness).
 */
final class CoursePresenter
{
    public function __construct(private readonly MakerDirectory $makers) {}

    /** @return array<string, mixed> */
    public function card(Course $course, ?array $maker = null): array
    {
        $course->loadMissing('categories');
        $category = $course->categories->first();
        $counts = $this->counts($course);

        return [
            'uuid' => $course->uuid,
            'slug' => $course->slug,
            ...$course->localized(),
            'cover_url' => $course->coverUrl(),
            'price' => ['amount_minor' => $course->price_amount_minor, 'currency' => $course->currency],
            'access_type' => $course->access_type->value,
            'access_days' => $course->access_days,
            'certificate_enabled' => $course->certificate_enabled,
            'lessons_count' => $counts['lessons'],
            'modules_count' => $counts['modules'],
            'category' => $category ? ['uuid' => $category->uuid, 'name' => $category->translate('name'), 'slug' => $category->slug] : null,
            'maker' => $maker ?? $this->makers->summaries([$course->maker_profile_uuid])[$course->maker_profile_uuid]?->toArray(),
            'published_at' => $course->published_at,
        ];
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int>} */
    public function page(LengthAwarePaginator $page): array
    {
        $makers = $this->makers->summaries($page->getCollection()->pluck('maker_profile_uuid')->unique()->values()->all());

        return [
            'data' => $page->getCollection()->map(fn (Course $c) => $this->card($c, $makers[$c->maker_profile_uuid]?->toArray()))->values()->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ];
    }

    /** Public course page: description and the outline (titles only, plus which lessons are free previews). */
    public function detail(Course $course): array
    {
        $course->loadMissing('modules.lessons.materials');

        return [
            ...$this->card($course),
            'status' => $course->status->value,
            'modules' => $course->modules->map(fn (CourseModule $m) => [
                'uuid' => $m->uuid,
                'title' => $m->title,
                'description' => $m->description,
                'lessons' => $m->lessons->map(fn (Lesson $l) => [
                    'uuid' => $l->uuid,
                    'title' => $l->title,
                    'is_preview' => $l->is_preview,
                    'is_required' => $l->is_required,
                    'material_types' => $l->materials->pluck('type')->map->value->unique()->values(),
                ])->values(),
            ])->values(),
        ];
    }

    /** Maker's editor and admin review: everything, untranslated, with what's still missing. */
    public function editor(Course $course, array $missing): array
    {
        $course->loadMissing('modules.lessons.materials', 'categories');

        return [
            ...$this->card($course),
            'name' => $course->name,
            'description' => $course->description,
            'content_locale' => $course->sourceLocale(),
            'translations' => (object) ($course->translations ?? []),
            'status' => $course->status->value,
            'review_note' => $course->review_note,
            'is_editable' => $course->status->isEditable(),
            'is_deletable' => $course->status->isDeletable(),
            'missing' => $missing,
            'students_count' => DB::table('enrollments')->where('course_uuid', $course->uuid)->count(),
            'modules' => $course->modules->map(fn (CourseModule $m) => [
                'uuid' => $m->uuid,
                'title' => $m->title,
                'description' => $m->description,
                'lessons' => $m->lessons->map(fn (Lesson $l) => [
                    ...$this->lesson($l),
                    'materials' => $l->materials->map(fn (LessonMaterial $mat) => $this->material($mat, editor: true))->values(),
                ])->values(),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    public function lesson(Lesson $lesson): array
    {
        return [
            'uuid' => $lesson->uuid,
            'title' => $lesson->title,
            'description' => $lesson->description,
            'is_required' => $lesson->is_required,
            'is_preview' => $lesson->is_preview,
        ];
    }

    /** @return array<string, mixed> */
    public function material(LessonMaterial $m, bool $editor = false): array
    {
        $video = $m->type === MaterialType::Video ? VideoUrl::parse((string) $m->external_url) : null;

        return [
            'uuid' => $m->uuid,
            'type' => $m->type->value,
            'title' => $m->title,
            'content' => $m->type === MaterialType::Text ? $m->content : null,
            'url' => $m->type === MaterialType::Link || ($editor && $m->type === MaterialType::Video) ? $m->external_url : null,
            'embed_url' => $video['embed'] ?? null,
            'provider' => $video['provider'] ?? null,
            'file' => $m->type === MaterialType::File ? ['name' => $m->file_name, 'size' => $m->file_size] : null,
        ];
    }

    /** @return array{modules: int, lessons: int} */
    private function counts(Course $course): array
    {
        if ($course->relationLoaded('modules') && $course->modules->every->relationLoaded('lessons')) {
            return ['modules' => $course->modules->count(), 'lessons' => $course->modules->sum(fn ($m) => $m->lessons->count())];
        }

        return [
            'modules' => $course->modules()->count(),
            'lessons' => Lesson::query()->whereIn('course_module_uuid', $course->modules()->select('uuid'))->count(),
        ];
    }
}
