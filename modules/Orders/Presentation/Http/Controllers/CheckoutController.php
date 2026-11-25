<?php

namespace Modules\Orders\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Identity\Application\Contracts\MakerSummary;
use Modules\Orders\Application\DTOs\DeliveryAddress;
use Modules\Orders\Application\Services\CartService;
use Modules\Orders\Application\Services\CheckoutService;
use Modules\Orders\Domain\Exceptions\OrderRuleViolation;
use Modules\Orders\Domain\PaymentMethod;
use Modules\Orders\Presentation\Http\Resources\OrderResource;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
    ) {}

    /** Price the cart for a destination before confirming. */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate(['country_code' => ['nullable', 'string', 'size:2', 'alpha']]);
        $cart = $this->carts->find($request->user(), null) ?? throw OrderRuleViolation::emptyCart();
        $quote = $this->checkout->quote($cart, isset($data['country_code']) ? strtoupper($data['country_code']) : null);

        $money = fn (int $m) => ['amount_minor' => $m, 'currency' => $quote['currency']];

        return response()->json(['data' => [
            'currency' => $quote['currency'],
            'needs_address' => $quote['needs_address'],
            'subtotal' => $money($quote['subtotal_minor']),
            'delivery' => $money($quote['delivery_minor']),
            'total' => $money($quote['total_minor']),
            'groups' => array_map(fn (array $g) => [
                'maker' => $g['maker'] instanceof MakerSummary ? $g['maker']->toArray() : null,
                'items_count' => array_sum(array_map(fn ($l) => $l['item']->quantity, $g['lines'])),
                'subtotal' => $money($g['subtotal_minor']),
                'delivery_fee' => $money($g['delivery_minor']),
                'total' => $money($g['total_minor']),
                'has_physical' => $g['has_physical'],
                'is_international' => $g['is_international'],
            ], $quote['groups']),
            'payment_methods' => collect($quote['payment_methods'])->map(fn (array $reasons, string $method) => [
                'method' => $method,
                'available' => $reasons === [],
                'reasons' => array_map(fn ($r) => __("domain.order.reasons.{$r}"), $reasons),
            ])->values(),
        ]]);
    }

    public function place(Request $request): JsonResponse
    {
        $cart = $this->carts->find($request->user(), null) ?? throw OrderRuleViolation::emptyCart();
        $needsAddress = $this->checkout->quote($cart, $request->input('delivery.country_code'))['needs_address'];

        $data = $request->validate([
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'expected_total_minor' => ['nullable', 'integer', 'min:0'],
            'delivery' => [$needsAddress ? 'required' : 'nullable', 'array'],
            'delivery.recipient_name' => [$needsAddress ? 'required' : 'nullable', 'string', 'max:160'],
            'delivery.phone' => [$needsAddress ? 'required' : 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'delivery.country_code' => [$needsAddress ? 'required' : 'nullable', 'string', 'size:2', 'alpha'],
            'delivery.city' => [$needsAddress ? 'required' : 'nullable', 'string', 'max:120'],
            'delivery.address' => [$needsAddress ? 'required' : 'nullable', 'string', 'max:255'],
            'delivery.postal_code' => ['nullable', 'string', 'max:20'],
        ], [], [
            'delivery.recipient_name' => __('validation.attributes.recipient_name'),
            'delivery.phone' => __('validation.attributes.phone'),
            'delivery.country_code' => __('validation.attributes.country_code'),
            'delivery.city' => __('validation.attributes.city'),
            'delivery.address' => __('validation.attributes.address'),
            'delivery.postal_code' => __('validation.attributes.postal_code'),
        ]);

        $order = $this->checkout->place(
            $request->user(),
            $cart,
            $needsAddress ? DeliveryAddress::fromArray($data['delivery']) : null,
            PaymentMethod::from($data['payment_method']),
            $data['expected_total_minor'] ?? null,
        );

        return OrderResource::make($order)->response()->setStatusCode(201);
    }
}
