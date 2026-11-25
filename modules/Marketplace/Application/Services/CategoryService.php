<?php

namespace Modules\Marketplace\Application\Services;

use Illuminate\Support\Str;
use Modules\Marketplace\Domain\Exceptions\CategoryRuleViolation;
use Modules\Marketplace\Infrastructure\Persistence\Category;

/**
 * Administrator category management. The tree has two levels (category → subcategory),
 * which is enough for the marketplace and keeps navigation simple.
 */
final class CategoryService
{
    /** @param array{name: string, parent_uuid?: ?string, description?: ?string, sort_order?: int, is_visible?: bool} $data */
    public function create(array $data): Category
    {
        $this->assertParentAllowed(null, $data['parent_uuid'] ?? null);

        return Category::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'sort_order' => $data['sort_order'] ?? $this->nextSortOrder($data['parent_uuid'] ?? null),
        ]);
    }

    /** @param array{name?: string, parent_uuid?: ?string, description?: ?string, sort_order?: int, is_visible?: bool} $data */
    public function update(Category $category, array $data): Category
    {
        if (array_key_exists('parent_uuid', $data)) {
            $this->assertParentAllowed($category, $data['parent_uuid']);
        }

        $category->fill($data);
        if ($category->isDirty('name')) {
            $category->slug = $this->uniqueSlug($category->name, ignore: $category->uuid);
        }
        $category->save();

        return $category;
    }

    public function delete(Category $category): void
    {
        if ($category->children()->exists()) {
            throw CategoryRuleViolation::hasChildren($category->name);
        }

        if (($used = $category->usageCount()) > 0) {
            throw CategoryRuleViolation::inUse($category->name, $used);
        }

        $category->delete();
    }

    private function assertParentAllowed(?Category $category, ?string $parentUuid): void
    {
        if ($parentUuid === null) {
            return;
        }

        if ($category && $parentUuid === $category->uuid) {
            throw CategoryRuleViolation::selfParent();
        }

        $parent = Category::query()->findOrFail($parentUuid);
        if ($parent->parent_uuid !== null) {
            throw CategoryRuleViolation::tooDeep();
        }

        if ($category && $category->children()->exists()) {
            throw CategoryRuleViolation::parentWithChildren();
        }
    }

    private function nextSortOrder(?string $parentUuid): int
    {
        return (int) Category::query()->where('parent_uuid', $parentUuid)->max('sort_order') + 1;
    }

    private function uniqueSlug(string $name, ?string $ignore = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (Category::query()->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
