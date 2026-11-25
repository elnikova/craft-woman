<?php

namespace Modules\Education\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Education\Domain\MaterialType;

class LessonMaterial extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'lesson_uuid', 'type', 'title', 'content', 'external_url',
        'storage_disk', 'storage_path', 'file_name', 'file_size', 'mime_type', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['type' => MaterialType::class, 'file_size' => 'integer'];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_uuid');
    }
}
