<?php

namespace Modules\Orders\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Identity\Application\Contracts\MakerSummary;
use Modules\Marketplace\Application\Contracts\ProductCatalog;
use Modules\Marketplace\Application\Contracts\PurchasableLine;
use Modules\Marketplace\Application\Services\InventoryService;
use Modules\Marketplace\Domain\Exceptions\ProductRuleViolation;
use Modules\Orders\Application\DTOs\DeliveryAddress;
use Modules\Orders\Domain\CheckoutGroupFacts;
use Modules\Orders\Domain\Exceptions\OrderRuleViolation;
use Modules\Orders\Domain\GroupStatus;
use Modules\Orders\Domain\OrderStatus;
use Modules\Orders\Domain\PaymentMethod;
use Modules\Orders\Domain\PaymentPolicy;
use Modules\Orders\Infrastructure\Persistence\Cart;
use Modules\Orders\Infrastructure\Persistence\CartItem;
use Modules\Orders\Infrastructure\Persistence\Order;

/**
 * Turns a cart into one Order with one OrderGroup per Maker (domain-rules.md §20–24).
 * The server recomputes everything; the client's totals are never trusted (§25).
 */
final class CheckoutService
{
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly MakerDirectory $makers,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Price the cart for a destination country: groups, delivery fees, totals and payment options.
     *
     * @return array<string, mixed>
     */
    public function quote(Cart $cart, ?string $countryCode): array
    {
        $items = $cart->items()->get();
        if ($items->isEmpty()) {
            throw OrderRuleViolation::emptyCart();
        }

        $lines = $this->catalog->lines($items->map(fn (CartItem $i) => [$i->product_uuid, $i->product_variant_uuid])->all());
        $makers = $this->makers->summaries(array_values(array_map(fn (PurchasableLine $l) => $l->makerUuid, $lines)));
        $currency = (string) $cart->currency;

        $groups = [];
        foreach ($items as $item) {
            $line = $lines["{$item->product_uuid}:{$item->product_variant_uuid}"] ?? null;
            $maker = $line ? ($makers[$line->makerUuid] ?? null) : null;

            if (! $line || ! $line->isPurchasable || ! ($maker?->isApproved ?? false)) {
                throw OrderRuleViolation::notPurchasable($line->productName ?? '—');
            }
            if (! $line->isDigital && $item->quantity > (int) $line->availableStock) {
                throw OrderRuleViolation::insufficientStock($line->productName, (int) $line->availableStock);
            }

            $groups[$maker->uuid] ??= ['maker' => $maker, 'lines' => [], 'subtotal' => 0, 'physical' => false, 'digital' => false];
            $groups[$maker->uuid]['lines'][] = ['item' => $item, 'line' => $line];
            $groups[$maker->uuid]['subtotal'] += $line->unitPriceMinor * $item->quantity;
            $groups[$maker->uuid][$line->isDigital ? 'digital' : 'physical'] = true;
        }

        $facts = [];
        $result = [];
        $subtotal = 0;
        $delivery = 0;
        $needsAddress = false;

        foreach ($groups as $g) {
            /** @var MakerSummary $maker */
            $maker = $g['maker'];
            $international = $g['physical'] && $countryCode !== null && $countryCode !== ($maker->countryCode ?? 'UA');
            $fee = 0;

            if ($g['physical']) {
                $needsAddress = true;
                if ($international && $maker->deliveryInternationalMinor === null) {
                    throw OrderRuleViolation::noInternationalShipping($maker->displayName);
                }
                if ($maker->deliveryCurrency !== $currency) {
                    throw OrderRuleViolation::deliveryCurrency($maker->displayName);
                }
                $fee = $international ? (int) $maker->deliveryInternationalMinor : $maker->deliveryDomesticMinor;
            }

            $facts[] = new CheckoutGroupFacts($maker->uuid, $g['physical'], $g['digital'], $international, $maker->codEnabled, $maker->deliveryInternationalMinor !== null);
            $subtotal += $g['subtotal'];
            $delivery += $fee;

            $result[] = [
                'maker' => $maker,
                'lines' => $g['lines'],
                'subtotal_minor' => $g['subtotal'],
                'delivery_minor' => $fee,
                'total_minor' => $g['subtotal'] + $fee,
                'has_physical' => $g['physical'],
                'is_international' => $international,
            ];
        }

        return [
            'currency' => $currency,
            'groups' => $result,
            'subtotal_minor' => $subtotal,
            'delivery_minor' => $delivery,
            'total_minor' => $subtotal + $delivery,
            'needs_address' => $needsAddress,
            'payment_methods' => PaymentPolicy::evaluate($facts),
            'facts' => $facts,
        ];
    }

    /**
     * Place the order: re-quote, check the payment rule, reserve stock, snapshot items, clear the cart.
     * $expectedTotalMinor guards against the cart changing between review and confirmation.
     */
    public function place(User $user, Cart $cart, ?DeliveryAddress $address, PaymentMethod $method, ?int $expectedTotalMinor = null): Order
    {
        return DB::transaction(function () use ($user, $cart, $address, $method, $expectedTotalMinor) {
            $quote = $this->quote($cart, $address?->countryCode);

            if ($expectedTotalMinor !== null && $expectedTotalMinor !== $quote['total_minor']) {
                throw OrderRuleViolation::cartChanged();
            }
            if (! PaymentPolicy::allows($method, $quote['facts'])) {
                throw OrderRuleViolation::paymentMethodUnavailable($quote['payment_methods'][$method->value]);
            }

            $online = $method === PaymentMethod::Online;
            $order = Order::create([
                'user_uuid' => $user->uuid,
                'order_number' => $this->orderNumber(),
                'currency' => $quote['currency'],
                'subtotal_amount_minor' => $quote['subtotal_minor'],
                'delivery_amount_minor' => $quote['delivery_minor'],
                'total_amount_minor' => $quote['total_minor'],
                'status' => $online ? OrderStatus::PendingPayment : OrderStatus::Processing,
                'payment_method' => $method,
                'billing_email' => $user->email,
                'placed_at' => now(),
            ]);

            foreach ($quote['groups'] as $g) {
                /** @var MakerSummary $maker */
                $maker = $g['maker'];
                $group = $order->groups()->create([
                    'maker_profile_uuid' => $maker->uuid,
                    'maker_name' => $maker->displayName,
                    'subtotal_amount_minor' => $g['subtotal_minor'],
                    'delivery_amount_minor' => $g['delivery_minor'],
                    'total_amount_minor' => $g['total_minor'],
                    // Payment on delivery is only possible for all-physical orders → straight to shipping
                    'status' => $online ? GroupStatus::PendingPayment : GroupStatus::AwaitingShipment,
                ]);

                foreach ($g['lines'] as ['item' => $item, 'line' => $line]) {
                    /** @var PurchasableLine $line */
                    if (! $line->isDigital) {
                        try {
                            $this->inventory->decrement($line->variantUuid, $item->quantity);
                        } catch (ProductRuleViolation) {
                            throw OrderRuleViolation::insufficientStock($line->productName, (int) $line->availableStock);
                        }
                    }

                    $group->items()->create([
                        'product_uuid' => $line->productUuid,
                        'product_variant_uuid' => $line->variantUuid,
                        'product_name' => $line->productName,
                        'variant_name' => $line->variantName,
                        'unit_price_amount_minor' => $line->unitPriceMinor,
                        'quantity' => $item->quantity,
                        'total_amount_minor' => $line->unitPriceMinor * $item->quantity,
                        'currency' => $line->currency,
                        'metadata' => [
                            'type' => $line->isDigital ? 'digital' : 'physical',
                            'slug' => $line->slug,
                            'image_url' => $line->imageUrl,
                        ],
                    ]);
                }

                if ($g['has_physical']) {
                    $group->delivery()->create($address->toArray());
                }
            }

            $cart->items()->delete();
            $cart->update(['currency' => null]);

            return $order->load('groups.items', 'groups.delivery');
        });
    }

    private function orderNumber(): string
    {
        do {
            $number = 'CW-'.now()->format('ym').'-'.Str::upper(Str::random(5));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
