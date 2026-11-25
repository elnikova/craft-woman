<?php

namespace Modules\Orders\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Orders\Application\Services\CartService;
use Modules\Orders\Application\Services\CheckoutService;
use Modules\Orders\Application\Services\OrderService;
use Modules\Orders\Infrastructure\Persistence\OrderGroup;
use Modules\Orders\Infrastructure\Persistence\OrderItem;

class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CartService::class);
        $this->app->singleton(CheckoutService::class);
        $this->app->singleton(OrderService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/Migrations');

        Route::model('group', OrderGroup::class);
        Route::model('item', OrderItem::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../../Presentation/Http/Routes/api.php');
    }
}
