<?php

namespace Modules\Marketplace\Presentation\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Marketplace\Infrastructure\Persistence\Product;

/** Builds product resources with Maker info fetched through Identity's read contract (one query per page). */
final class ProductPresenter
{
    public const RELATIONS = ['images', 'categories', 'variants', 'tags'];

    public function __construct(private readonly MakerDirectory $makers) {}

    public function one(Product $product, bool $private = false): ProductResource
    {
        $product->loadMissing([...self::RELATIONS, ...($private ? ['files'] : [])]);

        return ProductResource::make($product)
            ->withMaker($this->makers->summaries([$product->maker_profile_uuid])[$product->maker_profile_uuid] ?? null)
            ->asPrivate($private);
    }

    public function page(LengthAwarePaginator $page, bool $private = false): AnonymousResourceCollection
    {
        $makers = $this->makers->summaries($page->getCollection()->pluck('maker_profile_uuid')->all());
        $collection = ProductResource::collection($page);
        $collection->collection->each(fn (ProductResource $r) => $r->withMaker($makers[$r->maker_profile_uuid] ?? null)->asPrivate($private));

        return $collection;
    }
}
