<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Identity\Presentation\Http\Controllers\Admin\MakerReviewController;
use Modules\Identity\Presentation\Http\Controllers\MakerController;
use Modules\Identity\Presentation\Http\Controllers\MyMakerProfileController;

// Prefix /api/v1 and the `api` middleware group are applied by IdentityServiceProvider.

Route::get('/makers', [MakerController::class, 'index'])->name('makers.index');
Route::get('/makers/{slug}', [MakerController::class, 'show'])->name('makers.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me/maker-profile', [MyMakerProfileController::class, 'show'])->name('me.maker-profile.show');
    Route::put('/me/maker-profile', [MyMakerProfileController::class, 'upsert'])->name('me.maker-profile.upsert');
    Route::post('/me/maker-profile/submit', [MyMakerProfileController::class, 'submit'])->name('me.maker-profile.submit');

    Route::prefix('admin/makers')
        ->name('admin.makers.')
        ->middleware('can:review,'.MakerProfile::class)
        ->group(function () {
            Route::get('/', [MakerReviewController::class, 'index'])->name('index');
            Route::post('/{makerProfile}/approve', [MakerReviewController::class, 'approve'])->name('approve');
            Route::post('/{makerProfile}/reject', [MakerReviewController::class, 'reject'])->name('reject');
            Route::post('/{makerProfile}/suspend', [MakerReviewController::class, 'suspend'])->name('suspend');
            Route::post('/{makerProfile}/reinstate', [MakerReviewController::class, 'reinstate'])->name('reinstate');
        });
});
