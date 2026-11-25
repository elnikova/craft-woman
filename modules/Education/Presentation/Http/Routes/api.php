<?php

use Illuminate\Support\Facades\Route;
use Modules\Education\Presentation\Http\Controllers\Admin\CourseReviewController;
use Modules\Education\Presentation\Http\Controllers\CertificateController;
use Modules\Education\Presentation\Http\Controllers\CourseController;
use Modules\Education\Presentation\Http\Controllers\LearningController;
use Modules\Education\Presentation\Http\Controllers\Maker\CurriculumController;
use Modules\Education\Presentation\Http\Controllers\Maker\MyCourseController;

// Prefix /api/v1 and the `api` middleware group are applied by EducationServiceProvider.

Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{slug}', [CourseController::class, 'show'])->name('courses.show');
Route::get('/courses/{slug}/lessons/{lesson}', [CourseController::class, 'lesson'])->name('courses.lesson');
Route::get('/courses/{slug}/lessons/{lesson}/materials/{material}/file', [CourseController::class, 'file'])->name('courses.file');
Route::get('/certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me/learning', [LearningController::class, 'index'])->name('me.learning.index');
    Route::post('/courses/{slug}/lessons/{lesson}/complete', [LearningController::class, 'complete'])->name('courses.complete');
    Route::delete('/courses/{slug}/lessons/{lesson}/complete', [LearningController::class, 'uncomplete'])->name('courses.uncomplete');
    Route::post('/courses/{slug}/certificate', [LearningController::class, 'certificate'])->name('courses.certificate');

    Route::prefix('me')->name('me.courses.')->group(function () {
        Route::get('/courses', [MyCourseController::class, 'index'])->name('index');
        Route::post('/courses', [MyCourseController::class, 'store'])->name('store');
        Route::get('/courses/{course}', [MyCourseController::class, 'show'])->name('show');
        Route::put('/courses/{course}', [MyCourseController::class, 'update'])->name('update');
        Route::delete('/courses/{course}', [MyCourseController::class, 'destroy'])->name('destroy');
        Route::post('/courses/{course}/cover', [MyCourseController::class, 'cover'])->name('cover');
        Route::post('/courses/{course}/submit', [MyCourseController::class, 'submit'])->name('submit');
        Route::post('/courses/{course}/archive', [MyCourseController::class, 'archive'])->name('archive');
        Route::post('/courses/{course}/relist', [MyCourseController::class, 'relist'])->name('relist');

        Route::post('/courses/{course}/modules', [CurriculumController::class, 'storeModule'])->name('modules.store');
        Route::post('/courses/{course}/modules/reorder', [CurriculumController::class, 'reorderModules'])->name('modules.reorder');
        Route::patch('/course-modules/{module}', [CurriculumController::class, 'updateModule'])->name('modules.update');
        Route::delete('/course-modules/{module}', [CurriculumController::class, 'destroyModule'])->name('modules.destroy');
        Route::post('/course-modules/{module}/lessons', [CurriculumController::class, 'storeLesson'])->name('lessons.store');
        Route::post('/course-modules/{module}/lessons/reorder', [CurriculumController::class, 'reorderLessons'])->name('lessons.reorder');
        Route::patch('/lessons/{lesson}', [CurriculumController::class, 'updateLesson'])->name('lessons.update');
        Route::delete('/lessons/{lesson}', [CurriculumController::class, 'destroyLesson'])->name('lessons.destroy');
        Route::post('/lessons/{lesson}/materials', [CurriculumController::class, 'storeMaterial'])->name('materials.store');
        Route::patch('/lesson-materials/{material}', [CurriculumController::class, 'updateMaterial'])->name('materials.update');
        Route::delete('/lesson-materials/{material}', [CurriculumController::class, 'destroyMaterial'])->name('materials.destroy');
    });

    Route::middleware('can:manage-catalog')->prefix('admin/courses')->name('admin.courses.')->group(function () {
        Route::get('/', [CourseReviewController::class, 'index'])->name('index');
        Route::post('/{course}/approve', [CourseReviewController::class, 'approve'])->name('approve');
        Route::post('/{course}/reject', [CourseReviewController::class, 'reject'])->name('reject');
        Route::post('/{course}/archive', [CourseReviewController::class, 'archive'])->name('archive');
    });
});
