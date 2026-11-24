<?php

namespace Modules\Marketplace\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Identity\Application\Contracts\MakerSummary;
use Modules\Marketplace\Application\DTOs\ProductData;
use Modules\Marketplace\Domain\Exceptions\ProductRuleViolation;
use Modules\Marketplace\Domain\ProductReadiness;
use Modules\Marketplace\Domain\ProductStatus;
use Modules\Marketplace\Domain\ProductType;
use Modules\Marketplace\Infrastructure\Persistence\Product;
use Modules\Marketplace\Infrastructure\Persistence\ProductVariant;
use Modules\Marketplace\Infrastructure\Persistence\Tag;

/** Product lifecycle for Makers and moderators. Status rules live in ProductStatus. */
final class ProductService
{
    public function create(MakerSummary $maker, ProductData $data): Product
    {
        if (! $maker->isApproved) {
            throw ProductRuleViolation::makerNotApproved();
        }

        return DB::transaction(function () use ($maker, $data) {
            $product = new Product([
                'maker_profile_uuid' => $maker->uuid,
                'type' => $data->type ?? ProductType::Physical,
                'slug' => $this->uniqueSlug($data->name),
            ]);
            $product->status = ProductStatus::Draft;
            $this->applyFields($product, $data);
            $product->save();

            $this->syncRelations($product, $data);

            // A physical product always has at least one variant (schema §4.1)
            if ($product->type === ProductType::Physical && ! $product->variants()->exists()) {
                $product->variants()->create([
                    'name' => 'Стандартний',
                    'currency' => $product->currency,
                    'stock' => 1,
                    'is_default' => true,
                ]);
            }

            return $product->refresh();
        });
    }

    public function update(Product $product, ProductData $data): Product
    {
        if (! $product->status->isEditable()) {
            throw ProductRuleViolation::notEditable($product->status);
        }

        if ($data->type && $data->type !== $product->type) {
            // The type defines fulfilment; it may change only on a never-submitted draft
            if ($product->status !== ProductStatus::Draft || $product->published_at !== null) {
                throw ProductRuleViolation::typeLocked();
            }
            $product->type = $data->type;
        }

        return DB::transaction(function () use ($product, $data) {
            // The slug follows the name until the product has been published once — public links must not break
            if ($product->published_at === null && $product->name !== $data->name) {
                $product->slug = $this->uniqueSlug($data->name, ignore: $product->uuid);
            }
            $this->applyFields($product, $data);
            $product->save();
            $this->syncRelations($product, $data);

            if ($product->type === ProductType::Digital) {
                $product->variants()->delete(); // digital goods have no physical stock
            } elseif (! $product->variants()->exists()) {
                $product->variants()->create(['name' => 'Стандартний', 'currency' => $product->currency, 'stock' => 1, 'is_default' => true]);
            }

            return $product->refresh();
        });
    }

    public function submitForReview(Product $product): Product
    {
        $missing = ProductReadiness::missing(
            type: $product->type,
            name: $product->name,
            description: $product->description,
            priceMinor: $product->price_amount_minor,
            hasCategory: $product->categories()->exists(),
            imageCount: $product->images()->count(),
            activeVariantCount: $product->variants()->where('status', 'active')->count(),
            fileCount: $product->files()->count(),
        );

        // Check the transition first so "already in review" wins over "not ready"
        $next = $product->status->transitionTo(ProductStatus::PendingReview);

        if ($missing !== []) {
            throw ProductRuleViolation::notReady($missing);
        }

        return $this->setStatus($product, $next, null);
    }

    public function approve(Product $product): Product
    {
        $product->status = $product->status->transitionTo(ProductStatus::Published);
        $product->review_note = null;
        $product->published_at ??= now();
        $product->save();

        return $product;
    }

    public function reject(Product $product, string $note): Product
    {
        return $this->setStatus($product, $product->status->transitionTo(ProductStatus::Rejected), $note);
    }

    public function archive(Product $product, ?string $note = null): Product
    {
        return $this->setStatus($product, $product->status->transitionTo(ProductStatus::Archived), $note);
    }

    public function relist(Product $product): Product
    {
        return $this->setStatus($product, $product->status->transitionTo(ProductStatus::Draft), null);
    }

    public function delete(Product $product): void
    {
        if (! $product->status->isDeletable()) {
            throw ProductRuleViolation::notDeletable();
        }

        DB::transaction(function () use ($product) {
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->storage_path);
            }
            foreach ($product->files as $file) {
                Storage::disk($file->storage_disk)->delete($file->storage_path);
            }
            $product->forceDelete();
        });
    }

    private function setStatus(Product $product, ProductStatus $status, ?string $note): Product
    {
        $product->status = $status;
        $product->review_note = $note;
        $product->save();

        return $product;
    }

    private function applyFields(Product $product, ProductData $data): void
    {
        $product->fill([
            'name' => $data->name,
            'description' => $data->description,
            'price_amount_minor' => $data->priceMinor,
            'currency' => $data->currency,
            'production_time_days' => $product->type === ProductType::Physical ? $data->productionTimeDays : null,
            'shipping_info' => $product->type === ProductType::Physical ? $data->shippingInfo : null,
        ]);
    }

    private function syncRelations(Product $product, ProductData $data): void
    {
        if ($data->categoryUuid !== null) {
            $product->categories()->sync([$data->categoryUuid => ['created_at' => now()]]);
        }

        if ($data->tags !== null) {
            $product->tags()->sync(Tag::resolveNames($data->tags));
        }

        if ($data->variants !== null && $product->type === ProductType::Physical) {
            $this->syncVariants($product, $data);
        }
    }

    private function syncVariants(Product $product, ProductData $data): void
    {
        if ($data->variants === []) {
            throw ProductRuleViolation::lastVariant();
        }

        $keep = [];
        foreach (array_values($data->variants) as $i => $v) {
            $attributes = [
                'name' => trim($v['name']),
                'sku' => $v['sku'] ?? null,
                'price_amount_minor' => $v['price_amount_minor'] ?? null,
                'currency' => $product->currency,
                'stock' => max(0, (int) $v['stock']),
                'weight_grams' => $v['weight_grams'] ?? null,
                'status' => $v['status'] ?? 'active',
                'is_default' => $i === 0,
            ];

            $variant = isset($v['uuid'])
                ? $product->variants()->whereKey($v['uuid'])->first()
                : null;

            if ($variant) {
                $variant->update($attributes);
            } else {
                $variant = $product->variants()->create($attributes);
            }
            $keep[] = $variant->uuid;
        }

        $product->variants()->whereNotIn('uuid', $keep)->delete();
        ProductVariant::query()->whereIn('uuid', $keep)->update(['currency' => $product->currency]);
    }

    private function uniqueSlug(string $name, ?string $ignore = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 2;
        while (Product::withTrashed()->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
