<?php

namespace Modules\Orders\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Orders\Domain\OrderStatus;
use Modules\Orders\Infrastructure\Persistence\Order;
use Modules\Orders\Presentation\Http\Resources\OrderResource;

/** Platform-wide order list for administrators (read-only for now). */
class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'q' => ['nullable', 'string', 'max:60'],
        ]);

        return OrderResource::collection(
            Order::query()
                ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
                ->when($request->string('q')->toString(), fn ($q, $t) => $q->where(fn ($w) => $w->where('order_number', 'like', "%{$t}%")->orWhere('billing_email', 'like', "%{$t}%")))
                ->with('groups.items', 'groups.delivery')
                ->latest('placed_at')
                ->paginate(20)
        );
    }
}
