<?php

use Illuminate\Support\Facades\Route;
use Modules\Workshops\Presentation\Http\Controllers\Admin\WorkshopReviewController;
use Modules\Workshops\Presentation\Http\Controllers\BookingController;
use Modules\Workshops\Presentation\Http\Controllers\Maker\MyWorkshopController;
use Modules\Workshops\Presentation\Http\Controllers\WorkshopController;

// Prefix /api/v1 and the `api` middleware group are applied by WorkshopsServiceProvider.

Route::get('/workshops', [WorkshopController::class, 'index'])->name('workshops.index');
Route::get('/workshops/{slug}', [WorkshopController::class, 'show'])->name('workshops.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me/bookings', [BookingController::class, 'index'])->name('me.bookings.index');

    Route::prefix('me')->name('me.workshops.')->group(function () {
        Route::get('/workshops', [MyWorkshopController::class, 'index'])->name('index');
        Route::post('/workshops', [MyWorkshopController::class, 'store'])->name('store');
        Route::get('/workshops/{workshop}', [MyWorkshopController::class, 'show'])->name('show');
        Route::put('/workshops/{workshop}', [MyWorkshopController::class, 'update'])->name('update');
        Route::delete('/workshops/{workshop}', [MyWorkshopController::class, 'destroy'])->name('destroy');
        Route::post('/workshops/{workshop}/cover', [MyWorkshopController::class, 'cover'])->name('cover');
        Route::post('/workshops/{workshop}/submit', [MyWorkshopController::class, 'submit'])->name('submit');
        Route::post('/workshops/{workshop}/archive', [MyWorkshopController::class, 'archive'])->name('archive');
        Route::post('/workshops/{workshop}/relist', [MyWorkshopController::class, 'relist'])->name('relist');
        Route::post('/workshops/{workshop}/cancel', [MyWorkshopController::class, 'cancel'])->name('cancel');
        Route::get('/workshops/{workshop}/participants', [MyWorkshopController::class, 'participants'])->name('participants');
        Route::post('/workshops/{workshop}/sessions', [MyWorkshopController::class, 'storeSession'])->name('sessions.store');
        Route::put('/workshop-sessions/{session}', [MyWorkshopController::class, 'updateSession'])->name('sessions.update');
        Route::delete('/workshop-sessions/{session}', [MyWorkshopController::class, 'destroySession'])->name('sessions.destroy');
    });

    Route::middleware('can:manage-catalog')->prefix('admin/workshops')->name('admin.workshops.')->group(function () {
        Route::get('/', [WorkshopReviewController::class, 'index'])->name('index');
        Route::post('/{workshop}/approve', [WorkshopReviewController::class, 'approve'])->name('approve');
        Route::post('/{workshop}/reject', [WorkshopReviewController::class, 'reject'])->name('reject');
        Route::post('/{workshop}/archive', [WorkshopReviewController::class, 'archive'])->name('archive');
    });
});
