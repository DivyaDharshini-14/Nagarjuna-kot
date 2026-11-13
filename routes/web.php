<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CancellationRequestController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\KdsController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\KitchenMappingController;
use App\Http\Controllers\KotController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\MenuTypeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RestaurantTableController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Broadcasting\BroadcastController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/kds/{displayToken}', [KotController::class, 'registeredDisplay'])->name('kds.registered-display')->where('displayToken', '[A-Za-z0-9]{64}');
Route::post('/kds/{displayToken}/broadcasting/auth', [KotController::class, 'registeredDisplayBroadcastAuth'])
    ->middleware('throttle:30,1')->name('kds.broadcasting.auth')->where('displayToken', '[A-Za-z0-9]{64}');
Route::patch('/kds/{displayToken}/tickets/{kot}/status', [KotController::class, 'registeredDisplayUpdateStatus'])
    ->name('kds.registered-status')->where('displayToken', '[A-Za-z0-9]{64}');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('web.broadcasting.auth');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index')->middleware('permission:company.view');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create')->middleware('permission:company.add');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store')->middleware('permission:company.add');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit')->middleware('permission:company.edit');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update')->middleware('permission:company.edit');
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy')->middleware('permission:company.delete');

    Route::get('/branches/options', [BranchController::class, 'options'])->name('branches.options');
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index')->middleware('permission:branch.view');
    Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create')->middleware('permission:branch.add');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store')->middleware('permission:branch.add');
    Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit')->middleware('permission:branch.edit');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update')->middleware('permission:branch.edit');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy')->middleware('permission:branch.delete');

    Route::get('/kitchens/options', [KitchenController::class, 'options'])->name('kitchens.options');
    Route::get('/kitchens', [KitchenController::class, 'index'])->name('kitchens.index')->middleware('permission:kitchen.view');
    Route::get('/kitchens/create', [KitchenController::class, 'create'])->name('kitchens.create')->middleware('permission:kitchen.add');
    Route::post('/kitchens', [KitchenController::class, 'store'])->name('kitchens.store')->middleware('permission:kitchen.add');
    Route::get('/kitchens/{kitchen}/edit', [KitchenController::class, 'edit'])->name('kitchens.edit')->middleware('permission:kitchen.edit');
    Route::put('/kitchens/{kitchen}', [KitchenController::class, 'update'])->name('kitchens.update')->middleware('permission:kitchen.edit');
    Route::post('/kitchens/{kitchen}/display-registration', [KitchenController::class, 'registerDisplay'])->name('kitchens.display-register')->middleware('permission:kitchen.edit');
    Route::delete('/kitchens/{kitchen}/display-registration', [KitchenController::class, 'revokeDisplay'])->name('kitchens.display-revoke')->middleware('permission:kitchen.edit');
    Route::delete('/kitchens/{kitchen}', [KitchenController::class, 'destroy'])->name('kitchens.destroy')->middleware('permission:kitchen.delete');

    Route::get('/floors/options', [FloorController::class, 'options'])->name('floors.options');
    Route::get('/floors', [FloorController::class, 'index'])->name('floors.index')->middleware('permission:floor.view');
    Route::get('/floors/create', [FloorController::class, 'create'])->name('floors.create')->middleware('permission:floor.add');
    Route::post('/floors', [FloorController::class, 'store'])->name('floors.store')->middleware('permission:floor.add');
    Route::get('/floors/{floor}/edit', [FloorController::class, 'edit'])->name('floors.edit')->middleware('permission:floor.edit');
    Route::put('/floors/{floor}', [FloorController::class, 'update'])->name('floors.update')->middleware('permission:floor.edit');
    Route::delete('/floors/{floor}', [FloorController::class, 'destroy'])->name('floors.destroy')->middleware('permission:floor.delete');

    Route::get('/tables', [RestaurantTableController::class, 'index'])->name('tables.index')->middleware('permission:table.view');
    Route::get('/tables/create', [RestaurantTableController::class, 'create'])->name('tables.create')->middleware('permission:table.add');
    Route::post('/tables', [RestaurantTableController::class, 'store'])->name('tables.store')->middleware('permission:table.add');
    Route::get('/tables/{table}/edit', [RestaurantTableController::class, 'edit'])->name('tables.edit')->middleware('permission:table.edit');
    Route::put('/tables/{table}', [RestaurantTableController::class, 'update'])->name('tables.update')->middleware('permission:table.edit');
    Route::delete('/tables/{table}', [RestaurantTableController::class, 'destroy'])->name('tables.destroy')->middleware('permission:table.delete');

    Route::get('/categories/options', [CategoryController::class, 'options'])->name('categories.options');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index')->middleware('permission:category.view');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create')->middleware('permission:category.add');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store')->middleware('permission:category.add');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit')->middleware('permission:category.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update')->middleware('permission:category.edit');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy')->middleware('permission:category.delete');

    Route::get('/menu-types/options', [MenuTypeController::class, 'options'])->name('menu-types.options');
    Route::get('/menu-types', [MenuTypeController::class, 'index'])->name('menu-types.index')->middleware('permission:menu-type.view');
    Route::get('/menu-types/create', [MenuTypeController::class, 'create'])->name('menu-types.create')->middleware('permission:menu-type.add');
    Route::post('/menu-types', [MenuTypeController::class, 'store'])->name('menu-types.store')->middleware('permission:menu-type.add');
    Route::get('/menu-types/{menuType}/edit', [MenuTypeController::class, 'edit'])->name('menu-types.edit')->middleware('permission:menu-type.edit');
    Route::put('/menu-types/{menuType}', [MenuTypeController::class, 'update'])->name('menu-types.update')->middleware('permission:menu-type.edit');
    Route::delete('/menu-types/{menuType}', [MenuTypeController::class, 'destroy'])->name('menu-types.destroy')->middleware('permission:menu-type.delete');

    Route::get('/menu-items/options', [MenuItemController::class, 'options'])->name('menu-items.options');
    Route::get('/menu-items', [MenuItemController::class, 'index'])->name('menu-items.index')->middleware('permission:menu-item.view');
    Route::get('/menu-items/create', [MenuItemController::class, 'create'])->name('menu-items.create')->middleware('permission:menu-item.add');
    Route::post('/menu-items', [MenuItemController::class, 'store'])->name('menu-items.store')->middleware('permission:menu-item.add');
    Route::get('/menu-items/{menuItem}/edit', [MenuItemController::class, 'edit'])->name('menu-items.edit')->middleware('permission:menu-item.edit');
    Route::put('/menu-items/{menuItem}', [MenuItemController::class, 'update'])->name('menu-items.update')->middleware('permission:menu-item.edit');
    Route::delete('/menu-items/{menuItem}', [MenuItemController::class, 'destroy'])->name('menu-items.destroy')->middleware('permission:menu-item.delete');

    Route::get('/kitchen-mappings', [KitchenMappingController::class, 'index'])->name('kitchen-mappings.index')->middleware('permission:kitchen-mapping.view');
    Route::get('/kitchen-mappings/create', [KitchenMappingController::class, 'create'])->name('kitchen-mappings.create')->middleware('permission:kitchen-mapping.add');
    Route::post('/kitchen-mappings', [KitchenMappingController::class, 'store'])->name('kitchen-mappings.store')->middleware('permission:kitchen-mapping.add');
    Route::get('/kitchen-mappings/{kitchenMapping}/edit', [KitchenMappingController::class, 'edit'])->name('kitchen-mappings.edit')->middleware('permission:kitchen-mapping.edit');
    Route::put('/kitchen-mappings/{kitchenMapping}', [KitchenMappingController::class, 'update'])->name('kitchen-mappings.update')->middleware('permission:kitchen-mapping.edit');
    Route::delete('/kitchen-mappings/{kitchenMapping}', [KitchenMappingController::class, 'destroy'])->name('kitchen-mappings.destroy')->middleware('permission:kitchen-mapping.delete');

    // User Management
    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('permission:user.view');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create')->middleware('permission:user.add');
    Route::post('/users', [UserController::class, 'store'])->name('users.store')->middleware('permission:user.add');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('permission:user.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:user.edit');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:user.delete');

    // Role & Permission Management (Admin)
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:role.view');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('permission:role.add');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:role.add');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('permission:role.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:role.edit');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:role.delete');

    // Stock Management - Sub-Modules
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index')->middleware('permission:menu-item.view');
    Route::get('/stock/overview', [StockController::class, 'overview'])->name('stock.overview')->middleware('permission:menu-item.view');
    Route::get('/stock/stock-in', [StockController::class, 'stockInView'])->name('stock.stock-in')->middleware('permission:menu-item.view');
    Route::get('/stock/validation', [StockController::class, 'validation'])->name('stock.validation')->middleware('permission:menu-item.view');
    Route::get('/stock/adjustments', [StockController::class, 'adjustments'])->name('stock.adjustments')->middleware('permission:menu-item.view');
    Route::get('/stock/history', [StockController::class, 'history'])->name('stock.history')->middleware('permission:menu-item.view');
    Route::get('/stock/availability', [StockController::class, 'availabilityBoard'])->name('stock.availability-board')->middleware('permission:menu-item.view');

    // Stock Mutation Endpoints
    Route::post('/stock/{menuItem}/opening-stock', [StockController::class, 'setOpeningStock'])->name('stock.opening-stock')->middleware('permission:menu-item.stock-adjustment');
    Route::post('/stock/{menuItem}/stock-in', [StockController::class, 'recordStockIn'])->name('stock.record-stock-in')->middleware('permission:menu-item.stock-adjustment');
    Route::post('/stock/batch-stock-in', [StockController::class, 'batchStockIn'])->name('stock.batch-stock-in')->middleware('permission:menu-item.stock-adjustment');
    Route::post('/stock/{menuItem}/adjust', [StockController::class, 'adjust'])->name('stock.adjust')->middleware('permission:menu-item.stock-adjustment');
    Route::patch('/stock/{menuItem}/availability', [StockController::class, 'setAvailability'])->name('stock.availability')->middleware('permission:menu-item.stock-adjustment');
    Route::post('/stock/{menuItem}/toggle-tracking', [StockController::class, 'toggleTracking'])->name('stock.toggle-tracking')->middleware('permission:menu-item.stock-adjustment');
    Route::get('/stock/{menuItem}/transactions', [StockController::class, 'transactions'])->name('stock.transactions')->middleware('permission:menu-item.view');

    // Orders Management
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index')->middleware('permission:order.view');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create')->middleware('permission:order.add');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store')->middleware('permission:order.add');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show')->middleware('permission:order.view');
    Route::post('/orders/{order}/items', [OrderController::class, 'addItems'])->name('orders.add-items')->middleware('permission:order.add');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status')->middleware('permission:order.edit');

    Route::get('/cancellation-requests', [CancellationRequestController::class, 'index'])->name('cancellations.index')->middleware('permission:order.approve');
    Route::post('/cancellation-requests/{batch}/approve', [CancellationRequestController::class, 'approve'])->name('cancellations.approve')->middleware('permission:order.approve')->whereUuid('batch');
    Route::post('/cancellation-requests/{batch}/reject', [CancellationRequestController::class, 'reject'])->name('cancellations.reject')->middleware('permission:order.approve')->whereUuid('batch');

    // Reports Module
    Route::get('/reports/orders', [ReportController::class, 'orders'])->name('reports.orders')->middleware('permission:order.view');
    Route::get('/reports/orders/export', [ReportController::class, 'exportOrders'])->name('reports.orders.export')->middleware('permission:order.view');
    Route::get('/reports/menu-items', [ReportController::class, 'menuItems'])->name('reports.menu-items')->middleware('permission:order.view');
    Route::get('/reports/menu-items/export', [ReportController::class, 'exportMenuItems'])->name('reports.menu-items.export')->middleware('permission:order.view');
    Route::get('/reports/kots', [ReportController::class, 'kots'])->name('reports.kots')->middleware('permission:kot.view');
    Route::get('/reports/kots/export', [ReportController::class, 'exportKots'])->name('reports.kots.export')->middleware('permission:kot.view');

    // Kitchen Order Tickets (KOT) Management
    Route::get('/kots', [KotController::class, 'index'])->name('kots.index')->middleware('permission:kot.view');
    Route::patch('/kots/{kot}/status', [KotController::class, 'updateStatus'])->name('kots.update-status')->middleware('permission:kot.edit');
    Route::get('/kitchen-display/{kitchen}', [KdsController::class, 'screen'])->name('kots.display')->middleware('permission:kot.view');

    // Kitchen Display System (KDS) Module
    Route::get('/kds', [KdsController::class, 'index'])->name('kds.index')->middleware('permission:kot.view');
    Route::get('/kds/all', [KdsController::class, 'screenAll'])->name('kds.screen.all')->middleware('permission:kot.view');
    Route::get('/kds/feed', [KdsController::class, 'feed'])->name('kds.feed.all')->middleware('permission:kot.view');
    Route::get('/kds/dishes', [KdsController::class, 'menuDishes'])->name('kds.dishes.all')->middleware('permission:kot.view');
    Route::post('/kds/dishes/{menuItem}/toggle-availability', [KdsController::class, 'toggleDishAvailabilityDirect'])->name('kds.dishes.toggle.all')->middleware('permission:kot.edit');

    // Specific kitchen sub-resources (Must precede greedy generic {branchCode}/{kitchenCode})
    Route::get('/kds/{kitchen}/feed', [KdsController::class, 'feed'])->name('kds.feed')->whereNumber('kitchen')->middleware('permission:kot.view');
    Route::get('/kds/{kitchen}/dishes', [KdsController::class, 'menuDishes'])->name('kds.dishes')->whereNumber('kitchen')->middleware('permission:kot.view');
    Route::post('/kds/{kitchen}/dishes/{menuItem}/toggle-availability', [KdsController::class, 'toggleDishAvailability'])->name('kds.dishes.toggle')->middleware('permission:kot.edit');
    Route::post('/kds/tickets/{kot}/status', [KdsController::class, 'updateTicketStatus'])->name('kds.tickets.update-status')->middleware('permission:kot.edit');
    Route::post('/kds/items/{item}/status', [KdsController::class, 'updateItemStatus'])->name('kds.items.update-status')->middleware('permission:kot.edit');

    // Dedicated screen by ID (number only) - redirects to canonical /{branchCode}/{kitchenCode}
    Route::get('/kds/{kitchen}', [KdsController::class, 'screen'])->name('kds.screen')->whereNumber('kitchen')->middleware('permission:kot.view');

    // Backward-compatible redirect from /kds/{branchCode}/{kitchenCode} to canonical /{branchCode}/{kitchenCode}
    Route::get('/kds/{branchCode}/{kitchenCode}', function ($branchCode, $kitchenCode) {
        return redirect()->to('/' . $branchCode . '/' . $kitchenCode);
    })->middleware('permission:kot.view');

    // Canonical TV screen URL: base_url/{branch_code}/{kitchen_code} (Requirement Section 2.C & 6)
    Route::get('/{branchCode}/{kitchenCode}', [KdsController::class, 'screenByCode'])->name('kds.branch.kitchen')->middleware('permission:kot.view');
    Route::get('/{branchCode}/{kitchenCode}/kds', [KdsController::class, 'screenByCode'])->name('kds.friendly')->middleware('permission:kot.view');
});
