<?php

namespace Modules\Education\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Education\Application\Services\CourseService;
use Modules\Education\Domain\CourseStatus;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Education\Presentation\Http\Resources\CoursePresenter;

/** Course moderation (/admin/courses), the same flow as products. */
class CourseReviewController extends Controller
{
    public function __construct(
        private readonly CourseService $courses,
        private readonly CoursePresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::enum(CourseStatus::class)]]);
        $page = Course::query()
            ->where('status', $request->string('status')->toString() ?: CourseStatus::PendingReview->value)
            ->with('modules.lessons.materials', 'categories')
            ->oldest('updated_at')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Course $c) => $this->presenter->editor($c, []))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function approve(Course $course): JsonResponse
    {
        return $this->respond($this->courses->approve($course));
    }

    public function reject(Request $request, Course $course): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->respond($this->courses->reject($course, $data['note']));
    }

    public function archive(Request $request, Course $course): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->respond($this->courses->archive($course, $data['note']));
    }

    private function respond(Course $course): JsonResponse
    {
        return response()->json(['data' => $this->presenter->editor($course, [])]);
    }
}
