<?php

namespace Modules\Orders\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Orders\Infrastructure\Persistence\Order;
use Modules\Orders\Infrastructure\Persistence\OrderGroup;
use Modules\Orders\Infrastructure\Persistence\OrderItem;

/**
 * Full order for the customer (and admin). Makers get OrderGroupResource instead:
 * they only see their own part (domain-rules.md §21).
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $money = fn (int $minor) => ['amount_minor' => $minor, 'currency' => $this->currency];

        return [
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'payment_method' => $this->payment_method->value,
            'is_paid' => $this->paid_at !== null,
            'subtotal' => $money($this->subtotal_amount_minor),
            'delivery' => $money($this->delivery_amount_minor),
            'total' => $money($this->total_amount_minor),
            'billing_email' => $this->billing_email,
            'placed_at' => $this->placed_at,
            'paid_at' => $this->paid_at,
            'groups' => $this->whenLoaded('groups', fn () => $this->groups->map(fn (OrderGroup $g) => self::group($g, $this->resource))->values()),
        ];
    }

    /** @return array<string, mixed> */
    public static function group(OrderGroup $g, Order $order): array
    {
        $money = fn (int $minor) => ['amount_minor' => $minor, 'currency' => $order->currency];

        return [
            'uuid' => $g->uuid,
            'maker' => ['uuid' => $g->maker_profile_uuid, 'name' => $g->maker_name],
            'status' => $g->status->value,
            'cancel_reason' => $g->cancel_reason,
            'subtotal' => $money($g->subtotal_amount_minor),
            'delivery_fee' => $money($g->delivery_amount_minor),
            'total' => $money($g->total_amount_minor),
            'items' => $g->items->map(fn (OrderItem $i) => [
                'uuid' => $i->uuid,
                'product_uuid' => $i->product_uuid,
                'name' => $i->product_name,
                'variant_name' => $i->variant_name,
                'is_digital' => $i->isDigital() || $i->isCourse() || $i->isWorkshop(),
                'is_course' => $i->isCourse(),
                'course_uuid' => $i->course_uuid,
                'is_workshop' => $i->isWorkshop(),
                'workshop_uuid' => $i->workshop_uuid,
                'starts_at' => $i->metadata['starts_at'] ?? null,
                'slug' => $i->metadata['slug'] ?? null,
                'image_url' => $i->metadata['image_url'] ?? null,
                'quantity' => $i->quantity,
                'unit_price' => $money($i->unit_price_amount_minor),
                'total' => $money($i->total_amount_minor),
            ])->values(),
            'shipment' => $g->delivery ? [
                'recipient_name' => $g->delivery->recipient_name,
                'phone' => $g->delivery->phone,
                'country_code' => $g->delivery->country_code,
                'city' => $g->delivery->city,
                'address' => $g->delivery->address,
                'postal_code' => $g->delivery->postal_code,
                'tracking_number' => $g->delivery->tracking_number,
                'status' => $g->delivery->status,
                'shipped_at' => $g->delivery->shipped_at,
                'delivered_at' => $g->delivery->delivered_at,
            ] : null,
        ];
    }
}
