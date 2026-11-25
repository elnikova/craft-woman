<?php

namespace Modules\Marketplace\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Marketplace\Infrastructure\Persistence\Category;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'parent_uuid' => $this->parent_uuid,
            ...$this->localized(),
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'is_visible' => $this->is_visible,
            'children' => self::collection($this->whenLoaded('children')),
            // Admin screens edit the source text and every translation
            $this->mergeWhen($request->routeIs('admin.*'), fn () => [
                'source' => ['locale' => $this->sourceLocale(), 'name' => $this->name, 'description' => $this->description],
                'translations' => (object) ($this->translations ?? []),
            ]),
            'usage_count' => $this->when(isset($this->usage_count), fn () => (int) $this->usage_count),
        ];
    }
}
