<?php

namespace Modules\Education\Domain;

/**
 * What a course needs before review (domain-rules.md §12). Pure function over plain facts
 * so it can be tested without a database.
 */
final class CourseReadiness
{
    /**
     * @param  array{name: string, description: ?string, price_minor: int, access_type: AccessType, access_days: ?int, lessons: int, lessons_without_materials: int, required_lessons: int}  $f
     * @return list<string> missing requirement keys (empty = ready)
     */
    public static function missing(array $f): array
    {
        $missing = [];
        if (mb_strlen(trim($f['name'])) < 3) {
            $missing[] = 'name';
        }
        if (mb_strlen(trim((string) $f['description'])) < 30) {
            $missing[] = 'description';
        }
        if ($f['price_minor'] <= 0) {
            $missing[] = 'price';
        }
        if ($f['access_type'] === AccessType::Limited && ($f['access_days'] ?? 0) < 1) {
            $missing[] = 'access_days';
        }
        if ($f['lessons'] < 1) {
            $missing[] = 'lessons';
        } elseif ($f['lessons_without_materials'] > 0) {
            $missing[] = 'lesson_materials';
        }
        if ($f['lessons'] > 0 && $f['required_lessons'] < 1) {
            $missing[] = 'required_lessons';
        }

        return $missing;
    }
}
