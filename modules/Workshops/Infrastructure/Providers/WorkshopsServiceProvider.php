<?php

namespace Modules\Workshops\Infrastructure\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Orders\Application\Events\OrderPaid;
use Modules\Workshops\Application\Contracts\WorkshopCatalog;
use Modules\Workshops\Application\Listeners\ConfirmWorkshopBookings;
use Modules\Workshops\Application\Services\BookingService;
use Modules\Workshops\Application\Services\WorkshopService;
use Modules\Workshops\Infrastructure\Persistence\EloquentWorkshopCatalog;
use Modules\Workshops\Infrastructure\Persistence\Workshop;
use Modules\Workshops\Infrastructure\Persistence\WorkshopSession;

class WorkshopsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BookingService::class);
        $this->app->singleton(WorkshopService::class);
        $this->app->singleton(WorkshopCatalog::class, EloquentWorkshopCatalog::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/Migrations');

        Route::model('workshop', Workshop::class);
        Route::model('session', WorkshopSession::class);

        Event::listen(OrderPaid::class, ConfirmWorkshopBookings::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../../Presentation/Http/Routes/api.php');
    }
}
