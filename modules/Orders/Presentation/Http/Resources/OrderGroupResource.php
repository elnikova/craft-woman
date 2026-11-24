<?php

namespace Modules\Orders\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Orders\Infrastructure\Persistence\OrderGroup;

/**
 * One Maker's part of an order, as the Maker sees it: their items, the shipping address,
 * the order number and payment state — nothing about other Makers.
 *
 * @mixin OrderGroup
 */
class OrderGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->order;

        return [
            ...OrderResource::group($this->resource, $order),
            'order_number' => $order->order_number,
            'placed_at' => $order->placed_at,
            'payment_method' => $order->payment_method->value,
            'is_paid' => $order->paid_at !== null,
            'buyer_email' => $order->billing_email,
        ];
    }
}
