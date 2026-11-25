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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_visible' => $this->is_visible,
            'children' => self::collection($this->whenLoaded('children')),
            'usage_count' => $this->when(isset($this->usage_count), fn () => (int) $this->usage_count),
        ];
    }
}
