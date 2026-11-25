<?php

namespace Modules\Education\Application\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Education\Domain\Exceptions\CourseRuleViolation;
use Modules\Education\Domain\MaterialType;
use Modules\Education\Domain\VideoUrl;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Education\Infrastructure\Persistence\CourseModule;
use Modules\Education\Infrastructure\Persistence\Lesson;
use Modules\Education\Infrastructure\Persistence\LessonMaterial;

/** Modules → lessons → materials (PRD §6). Every change checks that the course is editable. */
final class CurriculumService
{
    public function __construct(private readonly CourseService $courses) {}

    /** @param array{title: string, description?: ?string} $data */
    public function addModule(Course $course, array $data): CourseModule
    {
        $this->courses->assertEditable($course);

        return $course->modules()->create([...$data, 'sort_order' => (int) $course->modules()->max('sort_order') + 1]);
    }

    /** @param array{title?: string, description?: ?string} $data */
    public function updateModule(CourseModule $module, array $data): CourseModule
    {
        $this->courses->assertEditable($module->course);

        return tap($module)->update($data);
    }

    public function deleteModule(CourseModule $module): void
    {
        $this->courses->assertEditable($module->course);
        DB::transaction(function () use ($module) {
            foreach ($module->lessons as $lesson) {
                $this->purgeFiles($lesson);
            }
            $module->delete();
        });
    }

    /** @param array{title: string, description?: ?string, is_required?: bool, is_preview?: bool} $data */
    public function addLesson(CourseModule $module, array $data): Lesson
    {
        $this->courses->assertEditable($module->course);

        return $module->lessons()->create([...$data, 'sort_order' => (int) $module->lessons()->max('sort_order') + 1]);
    }

    /** @param array<string, mixed> $data */
    public function updateLesson(Lesson $lesson, array $data): Lesson
    {
        $this->courses->assertEditable($lesson->module->course);

        return tap($lesson)->update($data);
    }

    public function deleteLesson(Lesson $lesson): void
    {
        $this->courses->assertEditable($lesson->module->course);
        DB::transaction(function () use ($lesson) {
            $this->purgeFiles($lesson);
            $lesson->delete();
        });
    }

    /**
     * Reorder modules of a course, or lessons of a module (uuids in the new order).
     *
     * @param  list<string>  $uuids
     */
    public function reorder(Course $course, string $what, array $uuids, ?CourseModule $module = null): void
    {
        $this->courses->assertEditable($course);
        DB::transaction(function () use ($course, $what, $uuids, $module) {
            $query = $what === 'modules' ? $course->modules() : $module->lessons();
            foreach (array_values($uuids) as $i => $uuid) {
                (clone $query)->whereKey($uuid)->update(['sort_order' => $i + 1]);
            }
        });
    }

    /** @param array{type: string, title?: ?string, content?: ?string, external_url?: ?string} $data */
    public function addMaterial(Lesson $lesson, array $data, ?UploadedFile $file = null): LessonMaterial
    {
        $this->courses->assertEditable($lesson->module->course);
        $type = MaterialType::from($data['type']);
        $attributes = ['type' => $type, 'title' => $data['title'] ?? null, 'sort_order' => (int) $lesson->materials()->max('sort_order') + 1];

        $attributes += match ($type) {
            MaterialType::Text => ['content' => $data['content'] ?? ''],
            MaterialType::Video => ['external_url' => $this->videoUrl($data['external_url'] ?? '')],
            MaterialType::Link => ['external_url' => $data['external_url'] ?? null],
            MaterialType::File => $this->storeFile($lesson, $file),
        };

        return $lesson->materials()->create($attributes);
    }

    /** @param array{title?: ?string, content?: ?string, external_url?: ?string} $data */
    public function updateMaterial(LessonMaterial $material, array $data): LessonMaterial
    {
        $this->courses->assertEditable($material->lesson->module->course);
        if ($material->type === MaterialType::Video && isset($data['external_url'])) {
            $data['external_url'] = $this->videoUrl($data['external_url']);
        }
        $allowed = match ($material->type) {
            MaterialType::Text => ['title', 'content'],
            MaterialType::Video, MaterialType::Link => ['title', 'external_url'],
            MaterialType::File => ['title'],
        };

        return tap($material)->update(array_intersect_key($data, array_flip($allowed)));
    }

    public function deleteMaterial(LessonMaterial $material): void
    {
        $this->courses->assertEditable($material->lesson->module->course);
        if ($material->storage_path) {
            Storage::disk($material->storage_disk)->delete($material->storage_path);
        }
        $material->delete();
    }

    private function videoUrl(string $url): string
    {
        if (VideoUrl::parse($url) === null) {
            throw CourseRuleViolation::invalidVideo();
        }

        return trim($url);
    }

    /** Lesson files are private: served only to students with access. @return array<string, mixed> */
    private function storeFile(Lesson $lesson, ?UploadedFile $file): array
    {
        abort_if($file === null, 422);

        return [
            'storage_disk' => 'local',
            'storage_path' => $file->store("course-files/{$lesson->uuid}", 'local'),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    private function purgeFiles(Lesson $lesson): void
    {
        foreach ($lesson->materials as $material) {
            if ($material->storage_path) {
                Storage::disk($material->storage_disk)->delete($material->storage_path);
            }
        }
    }
}
