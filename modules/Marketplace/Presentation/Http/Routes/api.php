<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketplace\Presentation\Http\Controllers\Admin\CategoryController;
use Modules\Marketplace\Presentation\Http\Controllers\Admin\ProductReviewController;
use Modules\Marketplace\Presentation\Http\Controllers\CatalogTaxonomyController;
use Modules\Marketplace\Presentation\Http\Controllers\FavoriteController;
use Modules\Marketplace\Presentation\Http\Controllers\Maker\MyProductController;
use Modules\Marketplace\Presentation\Http\Controllers\Maker\MyProductMediaController;
use Modules\Marketplace\Presentation\Http\Controllers\ProductController;

// Prefix /api/v1 and the `api` middleware group are applied by MarketplaceServiceProvider.

Route::get('/categories', [CatalogTaxonomyController::class, 'categories'])->name('categories.index');
Route::get('/tags', [CatalogTaxonomyController::class, 'tags'])->name('tags.index');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me/favorites', [FavoriteController::class, 'index'])->name('me.favorites.index');
    Route::get('/me/favorites/ids', [FavoriteController::class, 'ids'])->name('me.favorites.ids');
    Route::middleware('throttle:60,1')->group(function () {
        Route::put('/me/favorites/{product}', [FavoriteController::class, 'store'])->name('me.favorites.store');
        Route::delete('/me/favorites/{product}', [FavoriteController::class, 'destroy'])->name('me.favorites.destroy');
    });

    // Maker: own products
    Route::prefix('me/products')->name('me.products.')->group(function () {
        Route::get('/', [MyProductController::class, 'index'])->name('index');
        Route::post('/', [MyProductController::class, 'store'])->name('store');
        Route::get('/{product}', [MyProductController::class, 'show'])->name('show');
        Route::put('/{product}', [MyProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [MyProductController::class, 'destroy'])->name('destroy');
        Route::post('/{product}/submit', [MyProductController::class, 'submit'])->name('submit');
        Route::post('/{product}/archive', [MyProductController::class, 'archive'])->name('archive');
        Route::post('/{product}/relist', [MyProductController::class, 'relist'])->name('relist');

        Route::post('/{product}/images', [MyProductMediaController::class, 'storeImage'])->name('images.store');
        Route::put('/{product}/images/order', [MyProductMediaController::class, 'reorderImages'])->name('images.order');
        Route::delete('/{product}/images/{image}', [MyProductMediaController::class, 'destroyImage'])->name('images.destroy');
        Route::post('/{product}/files', [MyProductMediaController::class, 'storeFile'])->name('files.store');
        Route::delete('/{product}/files/{file}', [MyProductMediaController::class, 'destroyFile'])->name('files.destroy');
    });

    // Admin
    Route::middleware('can:manage-catalog')->prefix('admin')->name('admin.')->group(function () {
        Route::apiResource('categories', CategoryController::class)->except('show');

        Route::get('/products', [ProductReviewController::class, 'index'])->name('products.index');
        Route::post('/products/{product}/approve', [ProductReviewController::class, 'approve'])->name('products.approve');
        Route::post('/products/{product}/reject', [ProductReviewController::class, 'reject'])->name('products.reject');
        Route::post('/products/{product}/archive', [ProductReviewController::class, 'archive'])->name('products.archive');
    });
});
