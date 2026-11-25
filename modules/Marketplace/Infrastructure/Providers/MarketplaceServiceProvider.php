<?php

namespace Modules\Marketplace\Infrastructure\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Marketplace\Application\Services\CategoryService;
use Modules\Marketplace\Policies\CatalogAdminPolicy;

class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CategoryService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/Migrations');

        Gate::define('manage-catalog', [CatalogAdminPolicy::class, 'manage']);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../../Presentation/Http/Routes/api.php');
    }
}
