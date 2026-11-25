<?php

namespace Modules\Education\Infrastructure\Persistence;

use App\Support\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Education\Domain\AccessType;
use Modules\Education\Domain\CourseStatus;
use Modules\Marketplace\Infrastructure\Persistence\Category;

/**
 * @property string $uuid
 * @property string $maker_profile_uuid
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property CourseStatus $status
 * @property AccessType $access_type
 */
class Course extends Model
{
    use HasFactory, HasTranslations, HasUuids, SoftDeletes;

    /** @var list<string> */
    protected array $translatable = ['name', 'description'];

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'maker_profile_uuid', 'name', 'slug', 'description', 'price_amount_minor', 'currency',
        'access_type', 'access_days', 'certificate_enabled', 'content_locale',
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'access_type' => AccessType::class,
            'certificate_enabled' => 'boolean',
            'price_amount_minor' => 'integer',
            'access_days' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CourseFactory
    {
        return CourseFactory::new();
    }

    /** @return HasMany<CourseModule, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class, 'course_uuid')->orderBy('sort_order');
    }

    /** @return HasManyThrough<Lesson, CourseModule, $this> */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, CourseModule::class, 'course_uuid', 'course_module_uuid');
    }

    /** Categories are shared with products (marketplace taxonomy) through the `categorizables` table. */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable', 'categorizables', 'categorizable_uuid', 'category_uuid')
            ->withPivot('created_at');
    }

    /** @param Builder<self> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), CourseStatus::Published);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
