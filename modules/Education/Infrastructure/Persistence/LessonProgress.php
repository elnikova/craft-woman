<?php

namespace Modules\Education\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    use HasUuids;

    protected $table = 'lesson_progress';

    protected $primaryKey = 'uuid';

    protected $fillable = ['enrollment_uuid', 'lesson_uuid', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
