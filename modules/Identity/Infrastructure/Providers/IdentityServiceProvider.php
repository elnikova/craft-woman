<?php

namespace Modules\Identity\Infrastructure\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Application\Services\MakerProfileService;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Identity\Policies\MakerProfilePolicy;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MakerProfileService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/Migrations');

        Gate::policy(MakerProfile::class, MakerProfilePolicy::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../../Presentation/Http/Routes/api.php');
    }
}
