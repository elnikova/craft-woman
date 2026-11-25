<?php

namespace Modules\Education\Infrastructure\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Education\Application\Contracts\CourseCatalog;
use Modules\Education\Application\Listeners\GrantCourseAccess;
use Modules\Education\Application\Services\CourseService;
use Modules\Education\Application\Services\CurriculumService;
use Modules\Education\Application\Services\EnrollmentService;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Education\Infrastructure\Persistence\CourseModule;
use Modules\Education\Infrastructure\Persistence\EloquentCourseCatalog;
use Modules\Education\Infrastructure\Persistence\Lesson;
use Modules\Education\Infrastructure\Persistence\LessonMaterial;
use Modules\Orders\Application\Events\OrderPaid;

class EducationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CourseService::class);
        $this->app->singleton(CurriculumService::class);
        $this->app->singleton(EnrollmentService::class);
        $this->app->singleton(CourseCatalog::class, EloquentCourseCatalog::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/Migrations');

        Route::model('course', Course::class);
        Route::model('module', CourseModule::class);
        Route::model('lesson', Lesson::class);
        Route::model('material', LessonMaterial::class);

        Event::listen(OrderPaid::class, GrantCourseAccess::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/../../Presentation/Http/Routes/api.php');
    }
}
