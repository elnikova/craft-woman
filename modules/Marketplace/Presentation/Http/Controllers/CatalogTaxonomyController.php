<?php

namespace Modules\Marketplace\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Marketplace\Infrastructure\Persistence\Category;
use Modules\Marketplace\Infrastructure\Persistence\Tag;
use Modules\Marketplace\Presentation\Http\Resources\CategoryResource;
use Modules\Marketplace\Presentation\Http\Resources\TagResource;

/** Public categories tree and tag autocomplete. */
class CatalogTaxonomyController extends Controller
{
    public function categories(): AnonymousResourceCollection
    {
        $tree = Category::query()
            ->visible()
            ->whereNull('parent_uuid')
            ->with(['children' => fn ($q) => $q->visible()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($tree);
    }

    public function tags(Request $request): AnonymousResourceCollection
    {
        $term = mb_strtolower(trim($request->string('q')->toString()));

        return TagResource::collection(
            Tag::query()
                ->when($term !== '', fn ($q) => $q->whereLike('name', "{$term}%"))
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
    }
}
