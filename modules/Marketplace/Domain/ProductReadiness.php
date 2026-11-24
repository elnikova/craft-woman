<?php

namespace Modules\Marketplace\Domain;

/**
 * What a product needs before it can be sent to review. Pure domain logic:
 * the caller passes plain facts about the product.
 */
final class ProductReadiness
{
    /** @return list<string> missing requirement keys; empty = ready */
    public static function missing(
        ProductType $type,
        string $name,
        ?string $description,
        int $priceMinor,
        bool $hasCategory,
        int $imageCount,
        int $activeVariantCount,
        int $fileCount,
    ): array {
        $missing = [];

        if (mb_strlen(trim($name)) < 3) {
            $missing[] = 'name';
        }
        if (mb_strlen(trim((string) $description)) < 20) {
            $missing[] = 'description';
        }
        if ($priceMinor <= 0) {
            $missing[] = 'price';
        }
        if (! $hasCategory) {
            $missing[] = 'category';
        }
        if ($imageCount < 1) {
            $missing[] = 'image';
        }
        if ($type === ProductType::Physical && $activeVariantCount < 1) {
            $missing[] = 'variant';
        }
        if ($type === ProductType::Digital && $fileCount < 1) {
            $missing[] = 'file';
        }

        return $missing;
    }
}
