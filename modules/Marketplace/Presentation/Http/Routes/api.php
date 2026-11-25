<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketplace\Presentation\Http\Controllers\Admin\CategoryController;
use Modules\Marketplace\Presentation\Http\Controllers\CatalogTaxonomyController;

// Prefix /api/v1 and the `api` middleware group are applied by MarketplaceServiceProvider.

Route::get('/categories', [CatalogTaxonomyController::class, 'categories'])->name('categories.index');
Route::get('/tags', [CatalogTaxonomyController::class, 'tags'])->name('tags.index');

Route::middleware(['auth:sanctum', 'can:manage-catalog'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::apiResource('categories', CategoryController::class)->except('show');
    });
