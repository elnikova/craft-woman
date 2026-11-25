<?php

namespace Modules\Education\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['course_module_uuid', 'title', 'description', 'is_required', 'is_preview', 'sort_order'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_preview' => 'boolean'];
    }

    /** @return BelongsTo<CourseModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_uuid');
    }

    /** @return HasMany<LessonMaterial, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class, 'lesson_uuid')->orderBy('sort_order');
    }
}
