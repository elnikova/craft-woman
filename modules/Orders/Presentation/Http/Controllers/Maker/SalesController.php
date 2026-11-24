<?php

namespace Modules\Orders\Presentation\Http\Controllers\Maker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Orders\Application\Services\OrderService;
use Modules\Orders\Domain\GroupStatus;
use Modules\Orders\Infrastructure\Persistence\OrderGroup;
use Modules\Orders\Presentation\Http\Resources\OrderGroupResource;

/** The Maker's part of orders (/me/sales). A Maker only ever sees their own groups (domain-rules.md §21). */
class SalesController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly MakerDirectory $makers,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::enum(GroupStatus::class)]]);
        $maker = $this->makers->forUser($request->user()->uuid) ?? abort(403);

        return OrderGroupResource::collection(
            OrderGroup::query()
                ->where('maker_profile_uuid', $maker->uuid)
                ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
                ->with('order', 'items', 'delivery')
                ->latest()
                ->paginate(20)
        );
    }

    public function ship(Request $request, OrderGroup $group): OrderGroupResource
    {
        $this->own($request, $group);
        $data = $request->validate(['tracking_number' => ['nullable', 'string', 'max:64']]);

        return OrderGroupResource::make($this->orders->ship($group, $data['tracking_number'] ?? null)->load('order', 'items', 'delivery'));
    }

    public function deliver(Request $request, OrderGroup $group): OrderGroupResource
    {
        $this->own($request, $group);

        return OrderGroupResource::make($this->orders->deliver($group)->load('order', 'items', 'delivery'));
    }

    public function cancel(Request $request, OrderGroup $group): OrderGroupResource
    {
        $this->own($request, $group);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        return OrderGroupResource::make($this->orders->cancelByMaker($group, $data['reason'])->load('order', 'items', 'delivery'));
    }

    private function own(Request $request, OrderGroup $group): void
    {
        // 404, not 403: other Makers' orders don't exist for this Maker
        abort_unless($this->makers->forUser($request->user()->uuid)?->uuid === $group->maker_profile_uuid, 404);
    }
}
