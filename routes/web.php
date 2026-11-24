<?php

use Illuminate\Support\Facades\Route;

// Усі не-API маршрути віддають SPA-оболонку; маршрутизацією займається Vue Router.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|sanctum|up).*$')
    ->name('spa');
