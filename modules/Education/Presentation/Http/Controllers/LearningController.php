<?php

namespace Modules\Education\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Education\Application\Services\EnrollmentService;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Education\Infrastructure\Persistence\Enrollment;
use Modules\Education\Infrastructure\Persistence\Lesson;
use Modules\Education\Presentation\Http\Resources\CoursePresenter;

/** The student's side: my courses, progress and certificates. */
class LearningController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly CoursePresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = Enrollment::query()
            ->where('user_uuid', $request->user()->uuid)
            ->with('course.categories', 'certificate')
            ->latest('updated_at')
            ->get()
            ->map(fn (Enrollment $e) => [
                'course' => $this->presenter->card($e->course),
                'has_access' => $e->hasAccess(),
                'expires_at' => $e->expires_at,
                'completed_at' => $e->completed_at,
                'certificate_number' => $e->certificate?->certificate_number,
                ...$this->enrollments->progress($e),
            ]);

        return response()->json(['data' => $items]);
    }

    public function complete(Request $request, string $slug, Lesson $lesson): JsonResponse
    {
        [$enrollment] = $this->resolve($request, $slug, $lesson);

        return $this->progress($this->enrollments->complete($enrollment, $lesson));
    }

    public function uncomplete(Request $request, string $slug, Lesson $lesson): JsonResponse
    {
        [$enrollment] = $this->resolve($request, $slug, $lesson);

        return $this->progress($this->enrollments->uncomplete($enrollment, $lesson));
    }

    public function certificate(Request $request, string $slug): JsonResponse
    {
        $course = Course::withTrashed()->where('slug', $slug)->firstOrFail();
        $enrollment = $this->enrollments->find($request->user(), $course) ?? abort(404);

        return response()->json(['data' => CertificateController::present($this->enrollments->certificate($enrollment))], 201);
    }

    /** @return array{0: Enrollment, 1: Course} */
    private function resolve(Request $request, string $slug, Lesson $lesson): array
    {
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        abort_unless($lesson->module->course_uuid === $course->uuid, 404);

        return [$this->enrollments->requireAccess($request->user(), $course), $course];
    }

    private function progress(Enrollment $enrollment): JsonResponse
    {
        return response()->json(['data' => [
            'completed_at' => $enrollment->completed_at,
            ...$this->enrollments->progress($enrollment),
        ]]);
    }
}
