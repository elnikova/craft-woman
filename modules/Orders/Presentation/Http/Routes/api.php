<?php

use Illuminate\Support\Facades\Route;
use Modules\Orders\Presentation\Http\Controllers\Admin\OrderController;
use Modules\Orders\Presentation\Http\Controllers\CartController;
use Modules\Orders\Presentation\Http\Controllers\CheckoutController;
use Modules\Orders\Presentation\Http\Controllers\Maker\SalesController;
use Modules\Orders\Presentation\Http\Controllers\MyOrderController;

// Prefix /api/v1 and the `api` middleware group are applied by OrdersServiceProvider.

// Cart works for guests (X-Cart-Token) and signed-in users
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/items', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{cartItem}', [CartController::class, 'remove'])->name('cart.remove');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/cart/claim', [CartController::class, 'claim'])->name('cart.claim');

    Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->name('checkout.quote');
    Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');

    Route::get('/me/orders', [MyOrderController::class, 'index'])->name('me.orders.index');
    Route::get('/me/orders/{order}', [MyOrderController::class, 'show'])->name('me.orders.show');
    Route::post('/me/orders/{order}/cancel', [MyOrderController::class, 'cancel'])->name('me.orders.cancel');
    Route::post('/me/orders/{order}/test-payment', [MyOrderController::class, 'simulatePayment'])->name('me.orders.test-payment');
    Route::get('/me/orders/{order}/items/{item}/files/{file}', [MyOrderController::class, 'download'])->name('me.orders.download');

    Route::get('/me/sales', [SalesController::class, 'index'])->name('me.sales.index');
    Route::post('/me/sales/{group}/ship', [SalesController::class, 'ship'])->name('me.sales.ship');
    Route::post('/me/sales/{group}/deliver', [SalesController::class, 'deliver'])->name('me.sales.deliver');
    Route::post('/me/sales/{group}/cancel', [SalesController::class, 'cancel'])->name('me.sales.cancel');

    Route::middleware('can:manage-catalog')->get('/admin/orders', [OrderController::class, 'index'])->name('admin.orders.index');
});
