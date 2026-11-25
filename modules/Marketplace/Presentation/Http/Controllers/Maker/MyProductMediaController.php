<?php

namespace Modules\Marketplace\Presentation\Http\Controllers\Maker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Marketplace\Application\Services\ProductMediaService;
use Modules\Marketplace\Infrastructure\Persistence\Product;
use Modules\Marketplace\Infrastructure\Persistence\ProductFile;
use Modules\Marketplace\Infrastructure\Persistence\ProductImage;
use Modules\Marketplace\Presentation\Http\Resources\ProductPresenter;
use Modules\Marketplace\Presentation\Http\Resources\ProductResource;

/** Product photos (public disk) and digital files (private disk). Every call returns the updated product. */
class MyProductMediaController extends Controller
{
    public function __construct(
        private readonly ProductMediaService $media,
        private readonly ProductPresenter $presenter,
    ) {}

    public function storeImage(Request $request, Product $product): ProductResource
    {
        Gate::authorize('update', $product);
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'alt' => ['nullable', 'string', 'max:160'],
        ]);

        if ($product->images()->count() >= ProductMediaService::MAX_IMAGES) {
            abort(422, __('validation.max.array', ['attribute' => __('validation.attributes.image'), 'max' => ProductMediaService::MAX_IMAGES]));
        }

        $this->media->addImage($product, $request->file('image'), $request->input('alt'));

        return $this->presenter->one($product->refresh(), private: true);
    }

    public function destroyImage(Product $product, ProductImage $image): ProductResource
    {
        Gate::authorize('update', $product);
        abort_unless($image->product_uuid === $product->uuid, 404);
        $this->media->deleteImage($product, $image);

        return $this->presenter->one($product->refresh(), private: true);
    }

    public function reorderImages(Request $request, Product $product): ProductResource
    {
        Gate::authorize('update', $product);
        $request->validate(['order' => ['required', 'array'], 'order.*' => ['uuid']]);
        $this->media->reorderImages($product, $request->input('order'));

        return $this->presenter->one($product->refresh(), private: true);
    }

    public function storeFile(Request $request, Product $product): ProductResource
    {
        Gate::authorize('update', $product);
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,zip,jpg,jpeg,png,epub,txt,svg', 'max:51200'],
        ]);
        $this->media->addFile($product, $request->file('file'));

        return $this->presenter->one($product->refresh(), private: true);
    }

    public function destroyFile(Product $product, ProductFile $file): ProductResource
    {
        Gate::authorize('update', $product);
        abort_unless($file->product_uuid === $product->uuid, 404);
        $this->media->deleteFile($product, $file);

        return $this->presenter->one($product->refresh(), private: true);
    }
}
