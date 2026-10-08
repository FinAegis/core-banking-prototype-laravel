<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\PluginMarketplaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Module Management API Routes
|--------------------------------------------------------------------------
|
| Routes for the FinAegis module management system.
| Provides endpoints for listing, inspecting, enabling/disabling,
| and verifying health of domain modules.
|
*/

Route::prefix('v2/modules')->name('api.modules.')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [ModuleController::class, 'index'])->name('index');
        Route::get('/health', [ModuleController::class, 'health'])->name('health');
        Route::get('/{name}', [ModuleController::class, 'show'])->name('show');

        // Runtime module management: admins only, and only while
        // ADMIN_RUNTIME_MANAGEMENT_ENABLED is on (404 otherwise).
        Route::middleware(['feature:modules.runtime_management_enabled', 'is_admin'])->group(function () {
            Route::post('/{name}/enable', [ModuleController::class, 'enable'])->name('enable');
            Route::post('/{name}/disable', [ModuleController::class, 'disable'])->name('disable');
            Route::post('/{name}/verify', [ModuleController::class, 'verify'])->name('verify');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Plugin Marketplace API Routes
|--------------------------------------------------------------------------
|
| Routes for the FinAegis plugin marketplace system.
| Provides endpoints for listing, managing, scanning, and discovering plugins.
|
*/

Route::prefix('v2/plugins')->name('api.plugins.')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [PluginMarketplaceController::class, 'index'])->name('index');
        Route::get('/{id}', [PluginMarketplaceController::class, 'show'])->name('show');

        // Runtime plugin management: admins only, and only while
        // ADMIN_RUNTIME_MANAGEMENT_ENABLED is on (404 otherwise).
        Route::middleware(['feature:modules.runtime_management_enabled', 'is_admin'])->group(function () {
            Route::post('/{id}/enable', [PluginMarketplaceController::class, 'enable'])->name('enable');
            Route::post('/{id}/disable', [PluginMarketplaceController::class, 'disable'])->name('disable');
            Route::delete('/{id}', [PluginMarketplaceController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/scan', [PluginMarketplaceController::class, 'scan'])->name('scan');
            Route::post('/discover', [PluginMarketplaceController::class, 'discover'])->name('discover');
        });
    });
});
