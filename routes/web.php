<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MenuConfigController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecordSaleController;
use App\Http\Controllers\SalesHistoryController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route(auth()->user()->isAdmin() ? 'dashboard' : 'pos.index');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/media/{type}/{filename}', [MediaController::class, 'show'])
        ->whereIn('type', ['products', 'avatars'])
        ->where('filename', '[a-zA-Z0-9_-]+\.(jpg|webp)')
        ->name('media.show');

    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::get('/pos/customers/search', [PosController::class, 'searchCustomers'])->name('pos.customers.search');
    Route::get('/pos/gift-cards/lookup', [PosController::class, 'lookupGiftCard'])->name('pos.gift-cards.lookup');

    Route::get('/sales/record', [RecordSaleController::class, 'create'])->name('sales.record');
    Route::post('/sales/record', [RecordSaleController::class, 'store'])->name('sales.record.store');

    Route::get('/sales', [SalesHistoryController::class, 'index'])->name('sales.index');
    Route::get('/orders/{order}', [SalesHistoryController::class, 'showOrder'])->name('orders.show');
    Route::get('/orders/{order}/receipt', [SalesHistoryController::class, 'receipt'])->name('orders.receipt');

    Route::resource('customers', CustomerController::class);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/metrics', [DashboardController::class, 'metrics'])->name('dashboard.metrics');

        Route::resource('products', ProductController::class)->except(['show']);
        Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');
        Route::post('/products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');

        Route::resource('staff', StaffController::class)->except(['show']);

        Route::get('/menu-config', [MenuConfigController::class, 'index'])->name('menu-config.index');
        Route::post('/menu-config/categories', [MenuConfigController::class, 'storeCategory'])->name('menu-config.categories.store');
        Route::put('/menu-config/categories/{category}', [MenuConfigController::class, 'updateCategory'])->name('menu-config.categories.update');
        Route::delete('/menu-config/categories/{category}', [MenuConfigController::class, 'destroyCategory'])->name('menu-config.categories.destroy');

        Route::post('/menu-config/addons', [MenuConfigController::class, 'storeAddon'])->name('menu-config.addons.store');
        Route::put('/menu-config/addons/{addon}', [MenuConfigController::class, 'updateAddon'])->name('menu-config.addons.update');
        Route::delete('/menu-config/addons/{addon}', [MenuConfigController::class, 'destroyAddon'])->name('menu-config.addons.destroy');

        Route::post('/menu-config/products/{product}/variants', [MenuConfigController::class, 'storeVariant'])->name('menu-config.variants.store');
        Route::put('/menu-config/variants/{variant}', [MenuConfigController::class, 'updateVariant'])->name('menu-config.variants.update');
        Route::delete('/menu-config/variants/{variant}', [MenuConfigController::class, 'destroyVariant'])->name('menu-config.variants.destroy');

        Route::put('/menu-config/products/{product}/addons', [MenuConfigController::class, 'syncProductAddons'])->name('menu-config.products.addons.sync');
    });
});
