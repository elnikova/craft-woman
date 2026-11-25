<?php

namespace Modules\Education\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Education\Domain\EnrollmentStatus;

/**
 * @property string $uuid
 * @property string $user_uuid
 * @property string $course_uuid
 * @property ?\Illuminate\Support\Carbon $expires_at
 * @property ?\Illuminate\Support\Carbon $completed_at
 */
class Enrollment extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['user_uuid', 'course_uuid', 'order_item_uuid', 'starts_at', 'expires_at', 'status', 'completed_at'];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_uuid')->withTrashed();
    }

    /** @return HasMany<LessonProgress, $this> */
    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class, 'enrollment_uuid');
    }

    /** @return HasOne<Certificate, $this> */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class, 'enrollment_uuid');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Access is open while the enrollment isn't cancelled and hasn't expired (completion keeps access). */
    public function hasAccess(): bool
    {
        return $this->status !== EnrollmentStatus::Cancelled && ! $this->isExpired();
    }
}
