<?php

namespace Modules\Orders\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Marketplace\Application\Services\InventoryService;
use Modules\Orders\Application\Events\OrderPaid;
use Modules\Orders\Application\Purchasables\PurchasableCatalog;
use Modules\Orders\Domain\Exceptions\OrderRuleViolation;
use Modules\Orders\Domain\GroupStatus;
use Modules\Orders\Domain\OrderStatus;
use Modules\Orders\Domain\PaymentMethod;
use Modules\Orders\Infrastructure\Persistence\Order;
use Modules\Orders\Infrastructure\Persistence\OrderGroup;
use Modules\Orders\Infrastructure\Persistence\OrderItem;

/** After checkout: payment confirmation, cancellation, Maker fulfilment. */
final class OrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PurchasableCatalog $catalog,
    ) {}

    /** Called by the payment provider (Stripe webhook, next step) or the local test payment. */
    public function markPaid(Order $order): Order
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            return $order; // idempotent: webhooks may arrive twice
        }

        return DB::transaction(function () use ($order) {
            $order->paid_at = now();
            foreach ($order->groups()->with('items')->get() as $group) {
                // Digital-only parts are complete once paid; physical ones go to the Maker for shipping
                $group->status = $group->status->transitionTo($group->hasPhysical() ? GroupStatus::AwaitingShipment : GroupStatus::Completed);
                $group->save();
            }
            $order->unsetRelation('groups');
            $order->syncStatus();

            // Other modules react to payment: Education grants course access, Workshops confirm seats
            $items = OrderItem::query()->whereIn('order_group_uuid', $order->groups()->select('uuid'))->get(['uuid', 'course_uuid', 'workshop_uuid']);
            $courseItems = $items->whereNotNull('course_uuid')
                ->map(fn (OrderItem $i) => ['order_item_uuid' => $i->uuid, 'course_uuid' => $i->course_uuid])
                ->values()->all();
            $workshopItems = $items->whereNotNull('workshop_uuid')->pluck('uuid')->values()->all();
            DB::afterCommit(fn () => OrderPaid::dispatch($order->uuid, $order->user_uuid, $courseItems, $workshopItems));

            return $order;
        });
    }

    /** The customer may cancel while nothing has left any Maker and the order isn't paid online. */
    public function cancelByCustomer(Order $order): Order
    {
        $order->loadMissing('groups.items');
        $cancellable = $order->groups->every(fn (OrderGroup $g) => in_array($g->status, [GroupStatus::PendingPayment, GroupStatus::AwaitingShipment], true))
            && ($order->status === OrderStatus::PendingPayment || $order->payment_method === PaymentMethod::CashOnDelivery);

        if (! $cancellable) {
            throw OrderRuleViolation::notCancellable();
        }

        return DB::transaction(function () use ($order) {
            foreach ($order->groups as $group) {
                $this->cancelGroup($group, 'Скасовано покупцем');
            }
            $order->unsetRelation('groups');
            $order->syncStatus();

            return $order;
        });
    }

    public function ship(OrderGroup $group, ?string $trackingNumber): OrderGroup
    {
        return DB::transaction(function () use ($group, $trackingNumber) {
            $group->status = $group->status->transitionTo(GroupStatus::Shipped);
            $group->save();
            $group->delivery?->update(['status' => 'shipped', 'tracking_number' => $trackingNumber, 'shipped_at' => now()]);
            $group->order->syncStatus();

            return $group;
        });
    }

    public function deliver(OrderGroup $group): OrderGroup
    {
        return DB::transaction(function () use ($group) {
            $group->status = $group->status->transitionTo(GroupStatus::Delivered);
            $group->save();
            $group->delivery?->update(['status' => 'delivered', 'delivered_at' => now()]);

            // Payment on delivery: once everything is delivered, the money has been collected
            $order = $group->order;
            $order->syncStatus();
            if ($order->payment_method === PaymentMethod::CashOnDelivery && $order->status === OrderStatus::Completed) {
                $order->update(['paid_at' => now()]);
            }

            return $group;
        });
    }

    public function cancelByMaker(OrderGroup $group, string $reason): OrderGroup
    {
        if ($group->status !== GroupStatus::AwaitingShipment) {
            throw OrderRuleViolation::invalidGroupTransition($group->status, GroupStatus::Cancelled);
        }

        return DB::transaction(function () use ($group, $reason) {
            $this->cancelGroup($group, $reason);
            $group->order->syncStatus();

            return $group;
        });
    }

    public function canDownload(Order $order, OrderItem $item): bool
    {
        $group = $order->groups->firstWhere('uuid', $item->order_group_uuid);

        return $item->isDigital() && $order->paid_at !== null && $group && $group->status !== GroupStatus::Cancelled;
    }

    private function cancelGroup(OrderGroup $group, string $reason): void
    {
        $group->status = $group->status->transitionTo(GroupStatus::Cancelled);
        $group->cancel_reason = $reason;
        $group->save();

        // Return reserved stock and held workshop seats
        $items = $group->items()->get();
        foreach ($items as $item) {
            if ($item->isPhysical() && $item->product_variant_uuid) {
                $this->inventory->increment($item->product_variant_uuid, $item->quantity);
            }
        }
        $this->catalog->releaseSeats($items->filter->isWorkshop()->pluck('uuid')->values()->all(), $reason);
        $group->delivery?->update(['status' => 'cancelled']);
    }
}
