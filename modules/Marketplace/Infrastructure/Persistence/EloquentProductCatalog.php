<?php

namespace Modules\Marketplace\Infrastructure\Persistence;

use Modules\Marketplace\Application\Contracts\ProductCatalog;
use Modules\Marketplace\Application\Contracts\PurchasableLine;
use Modules\Marketplace\Domain\ProductType;

final class EloquentProductCatalog implements ProductCatalog
{
    public function line(string $productUuid, ?string $variantUuid = null): ?PurchasableLine
    {
        $product = Product::query()->with(['variants', 'images'])->find($productUuid);

        return $product ? $this->toLine($product, $variantUuid) : null;
    }

    public function lines(array $pairs): array
    {
        $products = Product::query()
            ->with(['variants', 'images'])
            ->whereIn('uuid', array_unique(array_column($pairs, 0)))
            ->get()
            ->keyBy('uuid');

        $result = [];
        foreach ($pairs as [$productUuid, $variantUuid]) {
            if ($product = $products->get($productUuid)) {
                $line = $this->toLine($product, $variantUuid);
                if ($line) {
                    $result["{$productUuid}:{$variantUuid}"] = $line;
                }
            }
        }

        return $result;
    }

    public function fileLocation(string $productUuid, string $fileUuid): ?array
    {
        $file = ProductFile::query()->where('product_uuid', $productUuid)->find($fileUuid);

        return $file ? ['disk' => $file->storage_disk, 'path' => $file->storage_path, 'name' => $file->file_name] : null;
    }

    public function files(string $productUuid): array
    {
        return ProductFile::query()
            ->where('product_uuid', $productUuid)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ProductFile $f) => ['uuid' => $f->uuid, 'name' => $f->file_name, 'size' => $f->file_size])
            ->all();
    }

    private function toLine(Product $product, ?string $variantUuid): ?PurchasableLine
    {
        $variant = null;
        if ($product->type === ProductType::Physical) {
            $variant = $variantUuid
                ? $product->variants->firstWhere('uuid', $variantUuid)
                : ($product->variants->first(fn ($v) => $v->is_default && $v->isActive()) ?? $product->variants->first->isActive());
            if (! $variant) {
                return null;
            }
        }

        return new PurchasableLine(
            productUuid: $product->uuid,
            variantUuid: $variant?->uuid,
            makerUuid: $product->maker_profile_uuid,
            isDigital: $product->type === ProductType::Digital,
            productName: $product->translate('name'),
            variantName: $variant && ($product->variants->count() > 1 || ! $variant->is_default) ? $variant->name : null,
            slug: $product->slug,
            imageUrl: $product->images->first()?->file_url,
            unitPriceMinor: $variant ? $variant->effectivePrice($product)->amountMinor : $product->price_amount_minor,
            currency: $product->currency->value,
            availableStock: $variant?->stock,
            isPurchasable: $product->status->isPurchasable() && ($variant === null || $variant->isActive()),
        );
    }
}
