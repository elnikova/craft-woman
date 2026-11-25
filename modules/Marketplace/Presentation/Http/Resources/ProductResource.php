<?php

namespace Modules\Marketplace\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Application\Contracts\MakerSummary;
use Modules\Marketplace\Infrastructure\Persistence\Product;
use Modules\Marketplace\Infrastructure\Persistence\ProductVariant;

/**
 * Public by default; owner/admin views (`$this->private`) add status, review note,
 * exact stock, SKUs and digital file metadata.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public ?MakerSummary $maker = null;

    public bool $private = false;

    public function withMaker(?MakerSummary $maker): static
    {
        $this->maker = $maker;

        return $this;
    }

    public function asPrivate(bool $private = true): static
    {
        $this->private = $private;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $category = $this->relationLoaded('categories') ? $this->categories->first() : null;

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price()->toArray(),
            'production_time_days' => $this->production_time_days,
            'shipping_info' => $this->shipping_info,
            'published_at' => $this->published_at,
            'category' => $category ? ['uuid' => $category->uuid, 'name' => $category->name, 'slug' => $category->slug] : null,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($i) => [
                'uuid' => $i->uuid,
                'url' => $i->file_url,
                'alt' => $i->alt_text ?? $this->name,
            ])->values()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants
                ->when(! $this->private, fn ($c) => $c->filter->isActive())
                ->map(fn (ProductVariant $v) => [
                    'uuid' => $v->uuid,
                    'name' => $v->name,
                    'price' => $v->effectivePrice($this->resource)->toArray(),
                    'in_stock' => $v->isPurchasable(),
                    // Exact stock is the Maker's business; buyers see "only N left" when it is low
                    'stock' => $this->private ? $v->stock : ($v->stock <= 3 ? $v->stock : null),
                    'is_default' => $v->is_default,
                    ...($this->private ? [
                        'sku' => $v->sku,
                        'price_override_minor' => $v->price_amount_minor,
                        'weight_grams' => $v->weight_grams,
                        'status' => $v->status,
                    ] : []),
                ])->values()),
            'maker' => $this->maker?->toArray(),
            $this->mergeWhen($this->private, fn () => [
                'status' => $this->status->value,
                'review_note' => $this->review_note,
                'is_editable' => $this->status->isEditable(),
                'is_deletable' => $this->status->isDeletable(),
                'files' => $this->whenLoaded('files', fn () => $this->files->map(fn ($f) => [
                    'uuid' => $f->uuid,
                    'name' => $f->file_name,
                    'size' => $f->file_size,
                    'mime_type' => $f->mime_type,
                ])->values()),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]),
            'is_digital' => $this->type->value === 'digital',
        ];
    }
}
