<?php

namespace Modules\Marketplace\Application\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Marketplace\Domain\Exceptions\ProductRuleViolation;
use Modules\Marketplace\Infrastructure\Persistence\Product;
use Modules\Marketplace\Infrastructure\Persistence\ProductFile;
use Modules\Marketplace\Infrastructure\Persistence\ProductImage;

/**
 * Images go to the public disk (served directly); digital files go to a private disk
 * and are delivered later through temporary links (domain-rules.md §10).
 */
final class ProductMediaService
{
    public const IMAGE_DISK = 'public';

    public const FILE_DISK = 'local';

    public const MAX_IMAGES = 10;

    public function addImage(Product $product, UploadedFile $file, ?string $alt = null): ProductImage
    {
        $this->assertEditable($product);

        $path = $file->store("products/{$product->uuid}", self::IMAGE_DISK);

        return $product->images()->create([
            'storage_path' => $path,
            'file_url' => Storage::disk(self::IMAGE_DISK)->url($path),
            'alt_text' => $alt,
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
        ]);
    }

    public function deleteImage(Product $product, ProductImage $image): void
    {
        $this->assertEditable($product);
        Storage::disk(self::IMAGE_DISK)->delete($image->storage_path);
        $image->delete();
    }

    /** @param list<string> $orderedUuids */
    public function reorderImages(Product $product, array $orderedUuids): void
    {
        $this->assertEditable($product);

        DB::transaction(function () use ($product, $orderedUuids) {
            foreach (array_values($orderedUuids) as $i => $uuid) {
                $product->images()->whereKey($uuid)->update(['sort_order' => $i]);
            }
        });
    }

    public function addFile(Product $product, UploadedFile $file): ProductFile
    {
        $this->assertEditable($product);

        $path = $file->store("product-files/{$product->uuid}", self::FILE_DISK);

        return $product->files()->create([
            'file_name' => $file->getClientOriginalName(),
            'storage_disk' => self::FILE_DISK,
            'storage_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'sort_order' => (int) $product->files()->max('sort_order') + 1,
        ]);
    }

    public function deleteFile(Product $product, ProductFile $file): void
    {
        $this->assertEditable($product);
        Storage::disk($file->storage_disk)->delete($file->storage_path);
        $file->delete();
    }

    private function assertEditable(Product $product): void
    {
        if (! $product->status->isEditable()) {
            throw ProductRuleViolation::notEditable($product->status);
        }
    }
}
