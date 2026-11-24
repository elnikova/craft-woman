<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
//Route::post('/social-login', [AuthController::class, 'socialLogin']);
//Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
//Route::post('/reset-password', [AuthController::class, 'resetPassword']);


//Route::get('/google/redirect', [AuthController::class, 'googleRedirect']);
//Route::get('/google/callback', [AuthController::class, 'googleCallback']);

