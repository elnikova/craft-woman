<?php

namespace Modules\Marketplace\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Marketplace\Domain\Exceptions\ProductRuleViolation;
use Modules\Marketplace\Infrastructure\Persistence\ProductVariant;

/**
 * Inventory operations (domain-rules.md §9): transactional, row-locked, never negative.
 * Orders will call this when a purchase is confirmed.
 */
final class InventoryService
{
    public function decrement(string $variantUuid, int $quantity): ProductVariant
    {
        return DB::transaction(function () use ($variantUuid, $quantity) {
            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()->whereKey($variantUuid)->lockForUpdate()->firstOrFail();

            if ($quantity < 1 || $variant->stock < $quantity) {
                throw ProductRuleViolation::insufficientStock($variant->name, $variant->stock, $quantity);
            }

            // Guarded update: even without row locks (SQLite), stock can't go below zero
            $affected = ProductVariant::query()
                ->whereKey($variantUuid)
                ->where('stock', '>=', $quantity)
                ->decrement('stock', $quantity);

            if ($affected === 0) {
                throw ProductRuleViolation::insufficientStock($variant->name, $variant->stock, $quantity);
            }

            return $variant->refresh();
        });
    }

    public function increment(string $variantUuid, int $quantity): ProductVariant
    {
        ProductVariant::query()->whereKey($variantUuid)->increment('stock', max(0, $quantity));

        return ProductVariant::query()->findOrFail($variantUuid);
    }
}
