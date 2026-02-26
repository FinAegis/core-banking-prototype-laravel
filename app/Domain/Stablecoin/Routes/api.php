<?php

declare(strict_types=1);

use App\Http\Controllers\Api\StablecoinController;
use App\Http\Controllers\Api\StablecoinOperationsController;
use Illuminate\Support\Facades\Route;

// Stablecoin endpoints
Route::prefix('v2')->group(function () {
    // Stablecoin management endpoints (requires stablecoins sub-product to be enabled)
    Route::prefix('stablecoins')->middleware('sub_product:stablecoins')->group(function () {
        Route::get('/', [StablecoinController::class, 'index']);
        Route::get('/metrics', [StablecoinController::class, 'systemMetrics']);
        Route::get('/health', [StablecoinController::class, 'systemHealth']);
        Route::get('/{code}', [StablecoinController::class, 'show']);
        Route::get('/{code}/metrics', [StablecoinController::class, 'metrics']);
        Route::get('/{code}/collateral-distribution', [StablecoinController::class, 'collateralDistribution']);
        Route::post('/{code}/execute-stability', [StablecoinController::class, 'executeStabilityMechanism']);

        // Admin operations
        Route::post('/', [StablecoinController::class, 'store']);
        Route::put('/{code}', [StablecoinController::class, 'update']);
        Route::post('/{code}/deactivate', [StablecoinController::class, 'deactivate']);
        Route::post('/{code}/reactivate', [StablecoinController::class, 'reactivate']);
    });

    // Stablecoin operations endpoints
    Route::middleware(['auth:sanctum', 'check.token.expiration', 'sub_product:stablecoins'])->prefix('stablecoin-operations')->group(function () {
        Route::post('/mint', [StablecoinOperationsController::class, 'mint'])->middleware('idempotency');
        Route::post('/burn', [StablecoinOperationsController::class, 'burn'])->middleware('idempotency');
        Route::post('/add-collateral', [StablecoinOperationsController::class, 'addCollateral'])->middleware('idempotency');

        // Position management
        Route::get('/accounts/{accountUuid}/positions', [StablecoinOperationsController::class, 'getAccountPositions']);
        Route::get('/positions/at-risk', [StablecoinOperationsController::class, 'getPositionsAtRisk']);
        Route::get('/positions/{positionUuid}', [StablecoinOperationsController::class, 'getPositionDetails']);

        // Liquidation operations
        Route::get('/liquidation/opportunities', [StablecoinOperationsController::class, 'getLiquidationOpportunities']);
        Route::post('/liquidation/execute', [StablecoinOperationsController::class, 'executeAutoLiquidation']);
        Route::post('/liquidation/positions/{positionUuid}', [StablecoinOperationsController::class, 'liquidatePosition']);
        Route::get('/liquidation/positions/{positionUuid}/reward', [StablecoinOperationsController::class, 'calculateLiquidationReward']);

        // Simulation and analytics
        Route::post('/simulation/{stablecoinCode}/mass-liquidation', [StablecoinOperationsController::class, 'simulateMassLiquidation']);
    });
});
