<?php

namespace Modules\Orders\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Marketplace\Application\Contracts\ProductCatalog;
use Modules\Marketplace\Application\Contracts\PurchasableLine;
use Modules\Orders\Domain\Exceptions\OrderRuleViolation;
use Modules\Orders\Infrastructure\Persistence\Cart;
use Modules\Orders\Infrastructure\Persistence\CartItem;

/**
 * The cart is a working list, not a commercial record (domain-rules.md §19).
 * Every read re-validates items against the current catalog and refreshes price snapshots.
 */
final class CartService
{
    public const MAX_QUANTITY = 99;

    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly MakerDirectory $makers,
    ) {}

    public function find(?User $user, ?string $token): ?Cart
    {
        if ($user) {
            return Cart::query()->where('user_uuid', $user->uuid)->first();
        }

        return $token ? Cart::query()->whereNull('user_uuid')->find($token) : null;
    }

    public function findOrCreate(?User $user, ?string $token): Cart
    {
        return $this->find($user, $token) ?? Cart::create(['user_uuid' => $user?->uuid]);
    }

    public function add(Cart $cart, string $productUuid, ?string $variantUuid, int $quantity): CartItem
    {
        $line = $this->catalog->line($productUuid, $variantUuid);
        $maker = $line ? ($this->makers->summaries([$line->makerUuid])[$line->makerUuid] ?? null) : null;

        if (! $line || ! $line->isPurchasable || ! $maker?->isApproved) {
            throw OrderRuleViolation::notPurchasable($line->productName ?? '—');
        }

        // domain-model.md §8.1: one currency per cart
        $cartIsEmpty = ! $cart->items()->exists();
        if (! $cartIsEmpty && $cart->currency !== $line->currency) {
            throw OrderRuleViolation::currencyMismatch((string) $cart->currency, $line->currency);
        }

        return DB::transaction(function () use ($cart, $line, $quantity, $cartIsEmpty) {
            if ($cartIsEmpty) {
                $cart->update(['currency' => $line->currency]);
            }

            $item = $cart->items()
                ->where('product_uuid', $line->productUuid)
                ->where('product_variant_uuid', $line->variantUuid)
                ->first();

            // Digital goods are bought once; physical quantity is capped by stock
            $qty = $line->isDigital ? 1 : min(($item?->quantity ?? 0) + $quantity, self::MAX_QUANTITY);
            if (! $line->isDigital && $qty > (int) $line->availableStock) {
                throw OrderRuleViolation::insufficientStock($line->productName, (int) $line->availableStock);
            }

            $attributes = ['quantity' => $qty, 'unit_price_amount_minor' => $line->unitPriceMinor, 'currency' => $line->currency];

            return $item
                ? tap($item)->update($attributes)
                : $cart->items()->create([...$attributes, 'product_uuid' => $line->productUuid, 'product_variant_uuid' => $line->variantUuid]);
        });
    }

    public function setQuantity(Cart $cart, CartItem $item, int $quantity): void
    {
        $line = $this->catalog->line($item->product_uuid, $item->product_variant_uuid);
        if ($line && ! $line->isDigital && $quantity > (int) $line->availableStock) {
            throw OrderRuleViolation::insufficientStock($line->productName, (int) $line->availableStock);
        }

        $item->update(['quantity' => $line?->isDigital ? 1 : min(max($quantity, 1), self::MAX_QUANTITY)]);
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        $item->delete();
        if (! $cart->items()->exists()) {
            $cart->update(['currency' => null]);
        }
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update(['currency' => null]);
    }

    /** After login: move a guest cart into the user's cart. Items that don't fit (currency, stock) are skipped. */
    public function merge(Cart $guest, User $user): Cart
    {
        $target = $this->findOrCreate($user, null);
        if ($guest->uuid === $target->uuid) {
            return $target;
        }

        foreach ($guest->items as $item) {
            try {
                $this->add($target, $item->product_uuid, $item->product_variant_uuid, $item->quantity);
            } catch (OrderRuleViolation) {
                // skipped silently: the cart view will show what's there
            }
        }
        $guest->delete();

        return $target->refresh();
    }

    /**
     * Current state of the cart, grouped by Maker, with an `issue` per line when it can't be bought as is.
     *
     * @return array{currency: ?string, items_count: int, subtotal_minor: int, groups: list<array<string, mixed>>, has_issues: bool}
     */
    public function view(Cart $cart): array
    {
        $items = $cart->items()->get();
        $lines = $this->catalog->lines($items->map(fn (CartItem $i) => [$i->product_uuid, $i->product_variant_uuid])->all());
        $makers = $this->makers->summaries(array_values(array_map(fn (PurchasableLine $l) => $l->makerUuid, $lines)));

        $groups = [];
        $subtotal = 0;
        $hasIssues = false;

        foreach ($items as $item) {
            $line = $lines["{$item->product_uuid}:{$item->product_variant_uuid}"] ?? null;
            $maker = $line ? ($makers[$line->makerUuid] ?? null) : null;

            $issue = match (true) {
                ! $line, ! $line->isPurchasable, ! ($maker?->isApproved ?? false) => 'unavailable',
                ! $line->isDigital && (int) $line->availableStock < 1 => 'sold_out',
                ! $line->isDigital && $item->quantity > (int) $line->availableStock => 'insufficient_stock',
                default => null,
            };

            $priceChanged = $line && $line->unitPriceMinor !== $item->unit_price_amount_minor;
            if ($priceChanged) {
                $item->update(['unit_price_amount_minor' => $line->unitPriceMinor]);
            }

            $hasIssues = $hasIssues || $issue !== null;
            $total = $item->unit_price_amount_minor * $item->quantity;
            if ($issue === null) {
                $subtotal += $total;
            }

            $key = $line?->makerUuid ?? 'unknown';
            $groups[$key] ??= ['maker' => $maker?->toArray(), 'items' => []];
            $groups[$key]['items'][] = [
                'uuid' => $item->uuid,
                'product_uuid' => $item->product_uuid,
                'variant_uuid' => $item->product_variant_uuid,
                'name' => $line?->productName ?? '—',
                'variant_name' => $line?->variantName,
                'slug' => $line?->slug,
                'image_url' => $line?->imageUrl,
                'is_digital' => $line?->isDigital ?? false,
                'quantity' => $item->quantity,
                'available_stock' => $line?->availableStock,
                'unit_price' => ['amount_minor' => $item->unit_price_amount_minor, 'currency' => $item->currency],
                'total' => ['amount_minor' => $total, 'currency' => $item->currency],
                'price_changed' => $priceChanged,
                'issue' => $issue,
            ];
        }

        return [
            'currency' => $cart->currency,
            'items_count' => (int) $items->sum('quantity'),
            'subtotal_minor' => $subtotal,
            'groups' => array_values($groups),
            'has_issues' => $hasIssues,
        ];
    }
}
