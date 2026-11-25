<?php

namespace Modules\Marketplace\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Marketplace\Application\Services\FavoriteService;
use Modules\Marketplace\Infrastructure\Persistence\Product;
use Modules\Marketplace\Presentation\Http\Resources\ProductPresenter;

/** The signed-in user's favorite products (/me/favorites). */
class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favorites,
        private readonly ProductPresenter $presenter,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->presenter->page(
            $this->favorites->products($request->user())->with(ProductPresenter::RELATIONS)->paginate(24)
        );
    }

    public function ids(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->favorites->ids($request->user())]);
    }

    public function store(Request $request, Product $product): Response
    {
        $this->favorites->add($request->user(), $product);

        return response()->noContent();
    }

    public function destroy(Request $request, string $product): Response
    {
        $this->favorites->remove($request->user(), $product);

        return response()->noContent();
    }
}
