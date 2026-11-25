<?php

namespace Modules\Marketplace\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Marketplace\Domain\ProductType;
use Modules\Marketplace\Infrastructure\Persistence\Category;
use Modules\Marketplace\Infrastructure\Persistence\Product;
use Modules\Marketplace\Presentation\Http\Resources\ProductPresenter;
use Modules\Marketplace\Presentation\Http\Resources\ProductResource;

/** Public catalog. */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductPresenter $presenter,
        private readonly MakerDirectory $makers,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'maker' => ['nullable', 'string', 'max:140'],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $query = Product::query()
            ->published()
            ->whereIn('maker_profile_uuid', $this->makers->approvedMakerUuidsQuery())
            ->with(ProductPresenter::RELATIONS);

        if ($slug = $request->string('category')->toString()) {
            $category = Category::query()->visible()->where('slug', $slug)->first();
            $uuids = $category?->selfAndDescendantUuids() ?? [];
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories.uuid', $uuids));
        }

        if ($slug = $request->string('maker')->toString()) {
            $query->where('maker_profile_uuid', $this->makers->findBySlug($slug)?->uuid);
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($term = trim($request->string('q')->toString())) {
            // Translations are stored as JSON text, so a plain LIKE also finds English names
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('translations', 'like', "%{$term}%")
                ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', "%{$term}%")));
        }

        match ($request->string('sort')->toString()) {
            'price_asc' => $query->orderBy('price_amount_minor'),
            'price_desc' => $query->orderByDesc('price_amount_minor'),
            default => $query->orderByDesc('published_at'),
        };

        return $this->presenter->page($query->paginate($request->integer('per_page', 12) ?: 12)->withQueryString());
    }

    public function show(Request $request, string $slug): ProductResource
    {
        $product = Product::query()->where('slug', $slug)->firstOrFail();
        $maker = $this->makers->summaries([$product->maker_profile_uuid])[$product->maker_profile_uuid] ?? null;
        $user = $request->user();

        $publiclyVisible = $product->status->isPurchasable() && ($maker?->isApproved ?? false);
        $privileged = $user !== null && ($user->isAdmin() || Gate::forUser($user)->allows('update', $product));

        // Hidden products are 404 for the public so their existence isn't revealed
        abort_unless($publiclyVisible || $privileged, 404);

        return $this->presenter->one($product, private: $privileged);
    }
}
