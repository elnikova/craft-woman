<?php

namespace Modules\Orders\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Modules\Marketplace\Application\Contracts\ProductCatalog;
use Modules\Orders\Application\Services\OrderService;
use Modules\Orders\Infrastructure\Persistence\Order;
use Modules\Orders\Infrastructure\Persistence\OrderItem;
use Modules\Orders\Presentation\Http\Resources\OrderResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The customer's orders (/me/orders). */
class MyOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ProductCatalog $catalog,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            Order::query()->where('user_uuid', $request->user()->uuid)->with('groups.items', 'groups.delivery')->latest('placed_at')->paginate(20)
        );
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $this->own($request, $order);

        return OrderResource::make($order->load('groups.items', 'groups.delivery'))
            ->additional(['downloads' => $this->downloads($order)]);
    }

    public function cancel(Request $request, Order $order): OrderResource
    {
        $this->own($request, $order);

        return OrderResource::make($this->orders->cancelByCustomer($order)->load('groups.items', 'groups.delivery'));
    }

    /**
     * Local/testing only: marks an online order as paid, until Stripe is connected.
     * Never registered as available in production.
     */
    public function simulatePayment(Request $request, Order $order): OrderResource
    {
        abort_unless(app()->environment('local', 'testing'), 404);
        $this->own($request, $order);

        return OrderResource::make($this->orders->markPaid($order)->load('groups.items', 'groups.delivery'))
            ->additional(['downloads' => $this->downloads($order)]);
    }

    /** Digital purchase download (domain-rules.md §10: only after payment). */
    public function download(Request $request, Order $order, OrderItem $item, string $file): StreamedResponse
    {
        $this->own($request, $order);
        $order->load('groups');
        abort_unless($item->order_group_uuid && $order->groups->contains('uuid', $item->order_group_uuid), 404);
        abort_unless($this->orders->canDownload($order, $item), 403);

        $location = $this->catalog->fileLocation((string) $item->product_uuid, $file) ?? abort(404);

        return Storage::disk($location['disk'])->download($location['path'], $location['name']);
    }

    /** @return array<string, list<array{uuid: string, name: string, size: int}>> keyed by order item uuid */
    private function downloads(Order $order): array
    {
        $order->loadMissing('groups.items');
        $result = [];
        foreach ($order->groups as $group) {
            foreach ($group->items as $item) {
                if ($item->product_uuid && $this->orders->canDownload($order, $item)) {
                    $result[$item->uuid] = $this->catalog->files($item->product_uuid);
                }
            }
        }

        return $result;
    }

    private function own(Request $request, Order $order): void
    {
        abort_unless($order->user_uuid === $request->user()->uuid, 404);
    }
}
