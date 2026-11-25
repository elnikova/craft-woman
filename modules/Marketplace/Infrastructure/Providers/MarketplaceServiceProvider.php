<?php

namespace Modules\Marketplace\Infrastructure\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Marketplace\Application\Services\CategoryService;
use Modules\Marketplace\Application\Services\InventoryService;
use Modules\Marketplace\Application\Services\ProductMediaService;
use Modules\Marketplace\Application\Services\ProductService;
use Modules\Marketplace\Infrastructure\Persistence\Product;
use Modules\Marketplace\Policies\CatalogAdminPolicy;
use Modules\Marketplace\Policies\ProductPolicy;

class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CategoryService::class);
        $this->app->singleton(ProductService::class);
        $this->app->singleton(ProductMediaService::class);
        $this->app->singleton(InventoryService::class);
        $this->app->singleton(\Modules\Marketplace\Application\Contracts\ProductCatalog::class, \Modules\Marketplace\Infrastructure\Persistence\EloquentProductCatalog::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/Migrations');

        Gate::define('manage-catalog', [CatalogAdminPolicy::class, 'manage']);
        Gate::policy(Product::class, ProductPolicy::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../../Presentation/Http/Routes/api.php');
    }
}
