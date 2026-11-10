<?php

use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileFloorController;
use App\Http\Controllers\Api\MobileMenuController;
use App\Http\Controllers\Api\MobileOrderController;
use App\Http\Controllers\Api\MobileKitchenController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes([
    'middleware' => ['auth:sanctum', 'account.active', 'abilities:kitchen:mobile'],
]);
require __DIR__.'/channels.php';

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('api.mobile.login');

    Route::post('/kitchen-mobile/auth/login', [MobileKitchenController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('api.kitchen-mobile.login');

    Route::middleware(['auth:sanctum', 'account.active', 'abilities:waiter:mobile'])->group(function () {
        Route::get('/auth/me', [MobileAuthController::class, 'me'])->name('api.mobile.me');
        Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('api.mobile.logout');

        Route::get('/floors', [MobileFloorController::class, 'index'])
            ->middleware(['permission:floor.view', 'permission:table.view'])
            ->name('api.mobile.floors.index');

        Route::get('/tables', [MobileFloorController::class, 'tables'])
            ->middleware('permission:table.view')
            ->name('api.mobile.tables.index');

        Route::get('/menu', [MobileMenuController::class, 'index'])
            ->middleware([
                'permission:category.view',
                'permission:menu-type.view',
                'permission:menu-item.view',
            ])
            ->name('api.mobile.menu.index');

        Route::get('/mobile/orders/{order}', [MobileOrderController::class, 'show'])
            ->middleware('permission:order.view')
            ->whereNumber('order')
            ->name('api.mobile.orders.show');

        Route::post('/mobile/orders', [MobileOrderController::class, 'store'])
            ->middleware('permission:order.add')
            ->name('api.mobile.orders.store');

        Route::post('/mobile/orders/{order}/items', [MobileOrderController::class, 'addItems'])
            ->middleware('permission:order.add')
            ->whereNumber('order')
            ->name('api.mobile.orders.add-items');
    });

    Route::middleware(['auth:sanctum', 'account.active', 'abilities:kitchen:mobile'])->prefix('kitchen-mobile')->group(function () {
        Route::get('/auth/me', [MobileKitchenController::class, 'me'])->name('api.kitchen-mobile.me');
        Route::post('/auth/logout', [MobileKitchenController::class, 'logout'])->name('api.kitchen-mobile.logout');
        Route::get('/kitchens', [MobileKitchenController::class, 'kitchens'])
            ->middleware('permission:kot.view')
            ->name('api.kitchen-mobile.kitchens.index');
        Route::get('/kitchens/{kitchen}/kot', [MobileKitchenController::class, 'tickets'])
            ->middleware('permission:kot.view')
            ->whereNumber('kitchen')
            ->name('api.kitchen-mobile.kot.index');
        Route::patch('/kot-tickets/{ticket}/status', [MobileKitchenController::class, 'updateTicketStatus'])
            ->middleware('permission:kot.edit')
            ->whereNumber('ticket')
            ->name('api.kitchen-mobile.kot-tickets.status');
    });
});

Route::prefix('v1')->middleware(['auth', 'account.active'])->group(function () {
    // Orders
    Route::post('/orders', [OrderApiController::class, 'store'])->name('api.orders.store')->middleware('permission:order.add');
    Route::get('/orders', [OrderApiController::class, 'index'])->name('api.orders.index')->middleware('permission:order.view');
    Route::get('/orders/{order}', [OrderApiController::class, 'show'])->name('api.orders.show')->middleware('permission:order.view');
    Route::post('/orders/{order}/items', [OrderApiController::class, 'addItems'])->name('api.orders.add-items')->middleware('permission:order.add');
    Route::get('/orders/{order}/kot', [OrderApiController::class, 'orderKot'])->name('api.orders.kot')->middleware('permission:order.view');

    // Kitchen Display / KOT
    Route::get('/kitchens/{kitchen}/kot', [OrderApiController::class, 'kitchenKot'])->name('api.kitchens.kot')->middleware('permission:kot.view');
    Route::patch('/kot-tickets/{ticket}/status', [OrderApiController::class, 'updateTicketStatus'])->name('api.kot-tickets.status')->middleware('permission:kot.edit');
    Route::patch('/kot-items/{item}/status', [OrderApiController::class, 'updateItemStatus'])->name('api.kot-items.status')->middleware('permission:kot.edit');
});
