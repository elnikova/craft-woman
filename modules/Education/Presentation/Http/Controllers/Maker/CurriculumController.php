<?php

namespace Modules\Education\Presentation\Http\Controllers\Maker;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Education\Application\Services\CourseService;
use Modules\Education\Application\Services\CurriculumService;
use Modules\Education\Domain\MaterialType;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Education\Infrastructure\Persistence\CourseModule;
use Modules\Education\Infrastructure\Persistence\Lesson;
use Modules\Education\Infrastructure\Persistence\LessonMaterial;
use Modules\Education\Presentation\Http\Resources\CoursePresenter;
use Modules\Identity\Application\Contracts\MakerDirectory;

/**
 * Curriculum builder: modules, lessons and materials. Every response returns the whole
 * course editor view, so the SPA always shows the saved state.
 */
class CurriculumController extends Controller
{
    public function __construct(
        private readonly CurriculumService $curriculum,
        private readonly CourseService $courses,
        private readonly CoursePresenter $presenter,
        private readonly MakerDirectory $makers,
    ) {}

    public function storeModule(Request $request, Course $course): JsonResponse
    {
        $this->own($request, $course);
        $this->curriculum->addModule($course, $request->validate($this->moduleRules(true)));

        return $this->editor($course, 201);
    }

    public function updateModule(Request $request, CourseModule $module): JsonResponse
    {
        $this->own($request, $module->course);
        $this->curriculum->updateModule($module, $request->validate($this->moduleRules(false)));

        return $this->editor($module->course);
    }

    public function destroyModule(Request $request, CourseModule $module): JsonResponse
    {
        $course = $module->course;
        $this->own($request, $course);
        $this->curriculum->deleteModule($module);

        return $this->editor($course);
    }

    public function reorderModules(Request $request, Course $course): JsonResponse
    {
        $this->own($request, $course);
        $data = $request->validate(['order' => ['required', 'array', 'max:100'], 'order.*' => ['uuid']]);
        $this->curriculum->reorder($course, 'modules', $data['order']);

        return $this->editor($course);
    }

    public function storeLesson(Request $request, CourseModule $module): JsonResponse
    {
        $this->own($request, $module->course);
        $this->curriculum->addLesson($module, $request->validate($this->lessonRules(true)));

        return $this->editor($module->course, 201);
    }

    public function updateLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $course = $lesson->module->course;
        $this->own($request, $course);
        $data = $request->validate([
            ...$this->lessonRules(false),
            // Moving a lesson to another module of the same course
            'course_module_uuid' => ['sometimes', 'uuid', Rule::exists('course_modules', 'uuid')->where('course_uuid', $course->uuid)],
        ]);
        $this->curriculum->updateLesson($lesson, $data);

        return $this->editor($course);
    }

    public function destroyLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $course = $lesson->module->course;
        $this->own($request, $course);
        $this->curriculum->deleteLesson($lesson);

        return $this->editor($course);
    }

    public function reorderLessons(Request $request, CourseModule $module): JsonResponse
    {
        $this->own($request, $module->course);
        $data = $request->validate(['order' => ['required', 'array', 'max:200'], 'order.*' => ['uuid']]);
        $this->curriculum->reorder($module->course, 'lessons', $data['order'], $module);

        return $this->editor($module->course);
    }

    public function storeMaterial(Request $request, Lesson $lesson): JsonResponse
    {
        $course = $lesson->module->course;
        $this->own($request, $course);
        $data = $request->validate([
            'type' => ['required', Rule::enum(MaterialType::class)],
            'title' => ['nullable', 'string', 'max:160'],
            'content' => ['required_if:type,text', 'nullable', 'string', 'max:50000'],
            'external_url' => ['required_if:type,video,link', 'nullable', 'url', 'max:500'],
            'file' => ['required_if:type,file', 'nullable', 'file', 'max:102400', 'mimes:pdf,zip,epub,jpg,jpeg,png,webp,txt,docx,pptx,xlsx'],
        ], [], ['external_url' => __('validation.attributes.link')]);
        $this->curriculum->addMaterial($lesson, $data, $request->file('file'));

        return $this->editor($course, 201);
    }

    public function updateMaterial(Request $request, LessonMaterial $material): JsonResponse
    {
        $course = $material->lesson->module->course;
        $this->own($request, $course);
        $this->curriculum->updateMaterial($material, $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'content' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'external_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ], [], ['external_url' => __('validation.attributes.link')]));

        return $this->editor($course);
    }

    public function destroyMaterial(Request $request, LessonMaterial $material): JsonResponse
    {
        $course = $material->lesson->module->course;
        $this->own($request, $course);
        $this->curriculum->deleteMaterial($material);

        return $this->editor($course);
    }

    /** @return array<string, list<string>> */
    private function moduleRules(bool $create): array
    {
        return [
            'title' => [$create ? 'required' : 'sometimes', 'string', 'min:2', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, list<string>> */
    private function lessonRules(bool $create): array
    {
        return [
            'title' => [$create ? 'required' : 'sometimes', 'string', 'min:2', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_required' => ['sometimes', 'boolean'],
            'is_preview' => ['sometimes', 'boolean'],
        ];
    }

    private function editor(Course $course, int $status = 200): JsonResponse
    {
        $course = $course->fresh();

        return response()->json(['data' => $this->presenter->editor($course, $this->courses->missingForReview($course))], $status);
    }

    private function own(Request $request, Course $course): void
    {
        abort_unless($this->makers->forUser($request->user()->uuid)?->uuid === $course->maker_profile_uuid, 404);
    }
}
