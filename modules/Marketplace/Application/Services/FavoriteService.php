<?php

namespace Modules\Marketplace\Application\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Marketplace\Infrastructure\Persistence\Favorite;
use Modules\Marketplace\Infrastructure\Persistence\Product;

class FavoriteService
{
    public function __construct(private readonly MakerDirectory $makers) {}

    /** Idempotent: saving twice keeps one record. Only products visible in the catalog can be saved. */
    public function add(User $user, Product $product): void
    {
        abort_unless($this->isVisible($product), 404);

        Favorite::query()->firstOrCreate(['user_uuid' => $user->uuid, 'product_uuid' => $product->uuid]);
    }

    public function remove(User $user, string $productUuid): void
    {
        Favorite::query()->where('user_uuid', $user->uuid)->where('product_uuid', $productUuid)->delete();
    }

    /** @return list<string> product UUIDs, newest first — the SPA uses them to fill the hearts */
    public function ids(User $user): array
    {
        return Favorite::query()->where('user_uuid', $user->uuid)->latest('created_at')->pluck('product_uuid')->all();
    }

    /**
     * Saved products still on sale, newest saved first. Products that were unpublished
     * keep their favorite record and reappear if they come back.
     *
     * @return Builder<Product>
     */
    public function products(User $user): Builder
    {
        return Product::query()
            ->published()
            ->whereIn('maker_profile_uuid', $this->makers->approvedMakerUuidsQuery())
            ->join('favorites', 'favorites.product_uuid', '=', 'products.uuid')
            ->where('favorites.user_uuid', $user->uuid)
            ->orderByDesc('favorites.created_at')
            ->select('products.*');
    }

    private function isVisible(Product $product): bool
    {
        return Product::query()
            ->whereKey($product->uuid)
            ->published()
            ->whereIn('maker_profile_uuid', $this->makers->approvedMakerUuidsQuery())
            ->exists();
    }
}
