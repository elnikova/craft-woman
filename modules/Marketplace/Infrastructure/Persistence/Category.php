<?php

namespace Modules\Marketplace\Infrastructure\Persistence;

use App\Support\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Administrator-managed marketplace category (domain-model.md §5.4). Hierarchical via parent_uuid.
 *
 * @property string $uuid
 * @property ?string $parent_uuid
 * @property string $name
 * @property string $slug
 * @property bool $is_visible
 * @property int $sort_order
 */
class Category extends Model
{
    use HasTranslations, HasUuids;

    /** @var list<string> */
    protected array $translatable = ['name', 'description'];

    protected $primaryKey = 'uuid';

    protected $fillable = ['parent_uuid', 'name', 'slug', 'description', 'sort_order', 'is_visible', 'content_locale'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'sort_order' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_uuid');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_uuid')->orderBy('sort_order')->orderBy('name');
    }

    /** @param Builder<self> $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true);
    }

    /** How many entities (products, courses…) are attached to this category. */
    public function usageCount(): int
    {
        return DB::table('categorizables')->where('category_uuid', $this->uuid)->count();
    }

    /**
     * UUIDs of this category and all its descendants (for "category includes subcategories" filters).
     *
     * @return list<string>
     */
    public function selfAndDescendantUuids(): array
    {
        $all = self::query()->get(['uuid', 'parent_uuid'])->groupBy('parent_uuid');
        $result = [$this->uuid];
        $queue = [$this->uuid];

        while ($queue) {
            $current = array_shift($queue);
            foreach ($all->get($current, new Collection) as $child) {
                $result[] = $child->uuid;
                $queue[] = $child->uuid;
            }
        }

        return $result;
    }
}
