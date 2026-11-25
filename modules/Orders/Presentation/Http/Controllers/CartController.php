<?php

namespace Modules\Orders\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Orders\Application\Services\CartService;
use Modules\Orders\Infrastructure\Persistence\Cart;
use Modules\Orders\Infrastructure\Persistence\CartItem;

/**
 * Signed-in users have one cart; guests are identified by the cart UUID they keep
 * in localStorage and send as `X-Cart-Token`. After login the guest cart is claimed.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->carts->find($request->user(), $this->token($request));

        return $this->respond($cart);
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_uuid' => ['required_without_all:course_uuid,workshop_uuid', 'nullable', 'uuid'],
            'course_uuid' => ['nullable', 'uuid'],
            'workshop_uuid' => ['nullable', 'uuid'],
            'variant_uuid' => ['nullable', 'uuid'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ]);

        $cart = $this->carts->findOrCreate($request->user(), $this->token($request));
        match (true) {
            isset($data['course_uuid']) => $this->carts->addCourse($cart, $data['course_uuid'], $request->user()),
            isset($data['workshop_uuid']) => $this->carts->addWorkshop($cart, $data['workshop_uuid'], $data['quantity'] ?? 1),
            default => $this->carts->add($cart, $data['product_uuid'], $data['variant_uuid'] ?? null, $data['quantity'] ?? 1),
        };

        return $this->respond($cart->refresh(), 201);
    }

    public function update(Request $request, string $cartItem): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY]]);
        [$cart, $item] = $this->owned($request, $cartItem);
        $this->carts->setQuantity($cart, $item, $data['quantity']);

        return $this->respond($cart);
    }

    public function remove(Request $request, string $cartItem): JsonResponse
    {
        [$cart, $item] = $this->owned($request, $cartItem);
        $this->carts->remove($cart, $item);

        return $this->respond($cart->refresh());
    }

    public function claim(Request $request): JsonResponse
    {
        $guest = $this->carts->find(null, $this->token($request));
        $cart = $guest ? $this->carts->merge($guest, $request->user()) : $this->carts->find($request->user(), null);

        return $this->respond($cart);
    }

    /** @return array{0: Cart, 1: CartItem} */
    private function owned(Request $request, string $itemUuid): array
    {
        $cart = $this->carts->find($request->user(), $this->token($request)) ?? abort(404);
        $item = $cart->items()->whereKey($itemUuid)->first() ?? abort(404);

        return [$cart, $item];
    }

    private function token(Request $request): ?string
    {
        $token = $request->header('X-Cart-Token');

        return is_string($token) && preg_match('/^[0-9a-f-]{36}$/i', $token) ? $token : null;
    }

    private function respond(?Cart $cart, int $status = 200): JsonResponse
    {
        $view = $cart ? $this->carts->view($cart) : ['currency' => null, 'items_count' => 0, 'subtotal_minor' => 0, 'groups' => [], 'has_issues' => false];

        return response()->json([
            'data' => [
                ...$view,
                // Guests store this token; signed-in users don't need it
                'token' => $cart && $cart->user_uuid === null ? $cart->uuid : null,
            ],
        ], $status);
    }
}
