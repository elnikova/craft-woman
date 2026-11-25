<?php

namespace Modules\Education\Presentation\Http\Controllers\Maker;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Modules\Education\Application\Services\CourseService;
use Modules\Education\Domain\AccessType;
use Modules\Education\Domain\CourseStatus;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Education\Presentation\Http\Resources\CoursePresenter;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Identity\Application\Contracts\MakerSummary;

/** The Maker's courses (/me/courses): details and lifecycle. */
class MyCourseController extends Controller
{
    public function __construct(
        private readonly CourseService $courses,
        private readonly CoursePresenter $presenter,
        private readonly MakerDirectory $makers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::enum(CourseStatus::class)]]);
        $maker = $this->maker($request);
        $page = Course::query()
            ->where('maker_profile_uuid', $maker->uuid)
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->with('categories')
            ->latest('updated_at')
            ->paginate(24);

        $data = $this->presenter->page($page);
        $statuses = $page->getCollection()->mapWithKeys(fn (Course $c) => [$c->uuid => ['status' => $c->status->value, 'review_note' => $c->review_note, 'name' => $c->name]]);
        $data['data'] = array_map(fn (array $c) => [...$c, ...$statuses[$c['uuid']]], $data['data']);

        return response()->json($data);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        $this->own($request, $course);

        return $this->editor($course);
    }

    public function store(Request $request): JsonResponse
    {
        $course = $this->courses->create($this->maker($request), $this->validated($request));

        return $this->editor($course, 201);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $this->own($request, $course);

        return $this->editor($this->courses->update($course, $this->validated($request)));
    }

    public function cover(Request $request, Course $course): JsonResponse
    {
        $this->own($request, $course);
        $request->validate(['cover' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:8192']]);

        return $this->editor($this->courses->setCover($course, $request->file('cover')));
    }

    public function submit(Request $request, Course $course): JsonResponse
    {
        $this->own($request, $course);

        return $this->editor($this->courses->submitForReview($course));
    }

    public function archive(Request $request, Course $course): JsonResponse
    {
        $this->own($request, $course);

        return $this->editor($this->courses->archive($course));
    }

    public function relist(Request $request, Course $course): JsonResponse
    {
        $this->own($request, $course);

        return $this->editor($this->courses->relist($course));
    }

    public function destroy(Request $request, Course $course): Response
    {
        $this->own($request, $course);
        $this->courses->delete($course);

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $required = $request->isMethod('post') ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'min:3', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'price_amount_minor' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'currency' => ['sometimes', 'in:EUR,USD,UAH'],
            'access_type' => ['sometimes', Rule::enum(AccessType::class)],
            'access_days' => ['nullable', 'required_if:access_type,limited', 'integer', 'min:1', 'max:3650'],
            'certificate_enabled' => ['sometimes', 'boolean'],
            'category_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:categories,uuid'],
            'content_locale' => ['sometimes', Rule::in(Locales::SUPPORTED)],
            'translations' => ['sometimes', 'nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'min:3', 'max:160'],
            'translations.*.description' => ['nullable', 'string', 'max:10000'],
        ], [], [
            'name' => __('validation.attributes.course_name'),
            'access_days' => __('validation.attributes.access_days'),
        ]);
    }

    private function editor(Course $course, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $this->presenter->editor($course->refresh(), $this->courses->missingForReview($course))], $status);
    }

    private function maker(Request $request): MakerSummary
    {
        return $this->makers->forUser($request->user()->uuid) ?? abort(403);
    }

    private function own(Request $request, Course $course): void
    {
        abort_unless($this->makers->forUser($request->user()->uuid)?->uuid === $course->maker_profile_uuid, 404);
    }
}
