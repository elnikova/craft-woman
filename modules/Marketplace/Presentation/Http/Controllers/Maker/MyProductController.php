<?php

namespace Modules\Marketplace\Presentation\Http\Controllers\Maker;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Marketplace\Application\Services\ProductService;
use Modules\Marketplace\Domain\ProductStatus;
use Modules\Marketplace\Infrastructure\Persistence\Product;
use Modules\Marketplace\Presentation\Http\Requests\ProductRequest;
use Modules\Marketplace\Presentation\Http\Resources\ProductPresenter;
use Modules\Marketplace\Presentation\Http\Resources\ProductResource;

/** The signed-in Maker's own products (/me/products). */
class MyProductController extends Controller
{
    public function __construct(
        private readonly ProductService $service,
        private readonly ProductPresenter $presenter,
        private readonly MakerDirectory $makers,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::enum(ProductStatus::class)]]);
        $maker = $this->makers->forUser($request->user()->uuid) ?? abort(403);

        $page = Product::query()
            ->where('maker_profile_uuid', $maker->uuid)
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->with(ProductPresenter::RELATIONS)
            ->latest('updated_at')
            ->paginate(20);

        return $this->presenter->page($page, private: true);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $maker = $this->makers->forUser($request->user()->uuid) ?? abort(403);
        $product = $this->service->create($maker, $request->toData());

        return $this->presenter->one($product, private: true)->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        return $this->presenter->one($product, private: true);
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        return $this->presenter->one($this->service->update($product, $request->toData()), private: true);
    }

    public function destroy(Product $product): JsonResponse
    {
        Gate::authorize('update', $product);
        $this->service->delete($product);

        return response()->json(null, 204);
    }

    public function submit(Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        return $this->presenter->one($this->service->submitForReview($product), private: true);
    }

    public function archive(Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        return $this->presenter->one($this->service->archive($product), private: true);
    }

    public function relist(Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        return $this->presenter->one($this->service->relist($product), private: true);
    }
}
