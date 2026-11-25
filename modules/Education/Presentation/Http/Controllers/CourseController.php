<?php

namespace Modules\Education\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Education\Application\Services\EnrollmentService;
use Modules\Education\Domain\CourseStatus;
use Modules\Education\Domain\MaterialType;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Education\Infrastructure\Persistence\Lesson;
use Modules\Education\Infrastructure\Persistence\LessonMaterial;
use Modules\Education\Presentation\Http\Resources\CoursePresenter;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Marketplace\Infrastructure\Persistence\Category;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Public course catalog, course pages and lesson content (/courses). */
class CourseController extends Controller
{
    public function __construct(
        private readonly CoursePresenter $presenter,
        private readonly EnrollmentService $enrollments,
        private readonly MakerDirectory $makers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'maker' => ['nullable', 'string', 'max:140'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $query = Course::query()->published()->whereIn('maker_profile_uuid', $this->makers->approvedMakerUuidsQuery())->with('categories');

        if ($term = trim($request->string('q')->toString())) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('translations', 'like', "%{$term}%"));
        }
        if ($slug = $request->string('category')->toString()) {
            $uuids = Category::query()->visible()->where('slug', $slug)->first()?->selfAndDescendantUuids() ?? [];
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories.uuid', $uuids));
        }
        if ($slug = $request->string('maker')->toString()) {
            $query->where('maker_profile_uuid', $this->makers->findBySlug($slug)?->uuid);
        }
        match ($request->string('sort')->toString()) {
            'price_asc' => $query->orderBy('price_amount_minor'),
            'price_desc' => $query->orderByDesc('price_amount_minor'),
            default => $query->orderByDesc('published_at'),
        };

        return response()->json($this->presenter->page($query->paginate($request->integer('per_page', 12) ?: 12)->withQueryString()));
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $course = $this->visible($request, $slug);
        $user = $request->user('sanctum');
        $enrollment = $user ? $this->enrollments->find($user, $course) : null;

        return response()->json(['data' => [
            ...$this->presenter->detail($course),
            'is_owner' => $this->isOwner($request, $course),
            'enrollment' => $enrollment ? [
                'has_access' => $enrollment->hasAccess(),
                'expires_at' => $enrollment->expires_at,
                'completed_at' => $enrollment->completed_at,
                ...$this->enrollments->progress($enrollment),
                'certificate_number' => $enrollment->certificate?->certificate_number,
            ] : null,
        ]]);
    }

    /** Lesson content for students, free previews, the owner and admins. */
    public function lesson(Request $request, string $slug, Lesson $lesson): JsonResponse
    {
        $course = $this->visible($request, $slug);
        $this->belongs($course, $lesson);
        abort_unless($this->enrollments->canView($request->user('sanctum'), $course, $lesson), 403);

        $ordered = $course->modules()->with('lessons:uuid,course_module_uuid,title,sort_order')->get()->flatMap->lessons->values();
        $index = $ordered->search(fn ($l) => $l->uuid === $lesson->uuid);

        $user = $request->user('sanctum');
        $enrollment = $user ? $this->enrollments->find($user, $course) : null;

        return response()->json(['data' => [
            ...$this->presenter->lesson($lesson),
            'module' => ['uuid' => $lesson->module->uuid, 'title' => $lesson->module->title],
            'materials' => $lesson->materials->map(fn (LessonMaterial $m) => $this->presenter->material($m))->values(),
            'completed' => $enrollment ? $enrollment->progress()->where('lesson_uuid', $lesson->uuid)->whereNotNull('completed_at')->exists() : false,
            'previous' => $index > 0 ? ['uuid' => $ordered[$index - 1]->uuid, 'title' => $ordered[$index - 1]->title] : null,
            'next' => $index !== false && $index < $ordered->count() - 1 ? ['uuid' => $ordered[$index + 1]->uuid, 'title' => $ordered[$index + 1]->title] : null,
        ]]);
    }

    public function file(Request $request, string $slug, Lesson $lesson, LessonMaterial $material): StreamedResponse
    {
        $course = $this->visible($request, $slug);
        $this->belongs($course, $lesson);
        abort_unless($material->lesson_uuid === $lesson->uuid && $material->type === MaterialType::File, 404);
        abort_unless($this->enrollments->canView($request->user('sanctum'), $course, $lesson), 403);

        return Storage::disk($material->storage_disk)->download($material->storage_path, $material->file_name);
    }

    /** Published courses for everyone; drafts only for their owner and admins (preview before publishing). */
    private function visible(Request $request, string $slug): Course
    {
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        $public = $course->status === CourseStatus::Published
            && in_array($course->maker_profile_uuid, $this->makers->approvedMakerUuidsQuery()->pluck('uuid')->all(), true);
        $enrolled = ($user = $request->user('sanctum')) && $this->enrollments->find($user, $course)?->hasAccess();

        abort_unless($public || $enrolled || $this->isOwner($request, $course) || $request->user('sanctum')?->isAdmin(), 404);

        return $course;
    }

    private function isOwner(Request $request, Course $course): bool
    {
        $user = $request->user('sanctum');

        return $user !== null && $this->makers->forUser($user->uuid)?->uuid === $course->maker_profile_uuid;
    }

    private function belongs(Course $course, Lesson $lesson): void
    {
        abort_unless($lesson->module->course_uuid === $course->uuid, 404);
    }
}
