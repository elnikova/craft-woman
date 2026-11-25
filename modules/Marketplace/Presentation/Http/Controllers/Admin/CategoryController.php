<?php

namespace Modules\Marketplace\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Marketplace\Application\Services\CategoryService;
use Modules\Marketplace\Infrastructure\Persistence\Category;
use Modules\Marketplace\Presentation\Http\Requests\CategoryRequest;
use Modules\Marketplace\Presentation\Http\Resources\CategoryResource;

/** Administrator category management: the full tree, hidden categories included. */
class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $usage = fn ($q) => $q->addSelect([
            'usage_count' => DB::table('categorizables')
                ->selectRaw('count(*)')
                ->whereColumn('categorizables.category_uuid', 'categories.uuid'),
        ]);

        $tree = Category::query()
            ->select('categories.*')
            ->tap($usage)
            ->whereNull('parent_uuid')
            ->with(['children' => fn ($q) => $q->select('categories.*')->tap($usage)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($tree);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        return CategoryResource::make($this->service->create($request->validated()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        return CategoryResource::make($this->service->update($category, $request->validated()));
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->service->delete($category);

        return response()->json(null, 204);
    }
}
