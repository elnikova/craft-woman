<?php

use Illuminate\Support\Facades\Route;

// Every non-API route serves the SPA shell; Vue Router handles routing.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|sanctum|up).*$')
    ->name('spa');
