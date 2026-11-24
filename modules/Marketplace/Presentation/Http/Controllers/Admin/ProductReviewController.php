<?php

namespace Modules\Marketplace\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Marketplace\Application\Services\ProductService;
use Modules\Marketplace\Domain\ProductStatus;
use Modules\Marketplace\Infrastructure\Persistence\Product;
use Modules\Marketplace\Presentation\Http\Resources\ProductPresenter;
use Modules\Marketplace\Presentation\Http\Resources\ProductResource;

/** Product moderation (domain-rules.md §6: admins moderate regardless of Maker permissions). */
class ProductReviewController extends Controller
{
    public function __construct(
        private readonly ProductService $service,
        private readonly ProductPresenter $presenter,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::enum(ProductStatus::class)]]);
        $status = $request->enum('status', ProductStatus::class) ?? ProductStatus::PendingReview;

        $page = Product::query()
            ->where('status', $status)
            ->with(ProductPresenter::RELATIONS)
            ->oldest('updated_at')
            ->paginate(20);

        return $this->presenter->page($page, private: true);
    }

    public function approve(Product $product): ProductResource
    {
        return $this->presenter->one($this->service->approve($product), private: true);
    }

    public function reject(Request $request, Product $product): ProductResource
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->presenter->one($this->service->reject($product, $data['note']), private: true);
    }

    public function archive(Request $request, Product $product): ProductResource
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->presenter->one($this->service->archive($product, $data['note']), private: true);
    }
}
