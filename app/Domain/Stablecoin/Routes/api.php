<?php

declare(strict_types=1);

use App\Http\Controllers\Api\StablecoinController;
use App\Http\Controllers\Api\StablecoinOperationsController;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

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

        // Configuration writes: stablecoin operators only
        // (StaffRoles::STABLECOIN_OPERATORS).
        Route::middleware(['auth:sanctum', RoleMiddleware::using(StaffRoles::STABLECOIN_OPERATORS)])->group(function () {
            Route::post('/{code}/execute-stability', [StablecoinController::class, 'executeStabilityMechanism']);
            Route::post('/', [StablecoinController::class, 'store']);
            Route::put('/{code}', [StablecoinController::class, 'update']);
            Route::post('/{code}/deactivate', [StablecoinController::class, 'deactivate']);
            Route::post('/{code}/reactivate', [StablecoinController::class, 'reactivate']);
        });
    });

    // Stablecoin operations endpoints
    Route::middleware(['auth:sanctum', 'sub_product:stablecoins'])->prefix('stablecoin-operations')->group(function () {
        // Position writes on an account the caller owns (checked in the
        // controller), behind the same flag as the other ledger funding paths.
        Route::middleware('feature:ledger.direct_funding_enabled')->group(function () {
            Route::post('/mint', [StablecoinOperationsController::class, 'mint'])->middleware('idempotency');
            Route::post('/burn', [StablecoinOperationsController::class, 'burn'])->middleware('idempotency');
            Route::post('/add-collateral', [StablecoinOperationsController::class, 'addCollateral'])->middleware('idempotency');
        });

        // Position management (owner or stablecoin operator, checked in the
        // controller)
        Route::get('/accounts/{accountUuid}/positions', [StablecoinOperationsController::class, 'getAccountPositions']);

        // Platform-wide risk, liquidation and simulation endpoints: stablecoin
        // operators only (StaffRoles::STABLECOIN_OPERATORS). They list every
        // user's positions, and a liquidation seizes the owner's collateral.
        // Staff liquidations pay no liquidator reward (see the controller).
        Route::middleware(RoleMiddleware::using(StaffRoles::STABLECOIN_OPERATORS))->group(function () {
            Route::get('/positions/at-risk', [StablecoinOperationsController::class, 'getPositionsAtRisk']);
            Route::get('/liquidation/opportunities', [StablecoinOperationsController::class, 'getLiquidationOpportunities']);
            Route::get('/liquidation/positions/{positionUuid}/reward', [StablecoinOperationsController::class, 'calculateLiquidationReward']);
            Route::post('/simulation/{stablecoinCode}/mass-liquidation', [StablecoinOperationsController::class, 'simulateMassLiquidation']);
        });

        // Liquidation moves ledger balances: also behind the ledger flag.
        Route::middleware(['feature:ledger.direct_funding_enabled', RoleMiddleware::using(StaffRoles::STABLECOIN_OPERATORS)])->group(function () {
            Route::post('/liquidation/execute', [StablecoinOperationsController::class, 'executeAutoLiquidation']);
            Route::post('/liquidation/positions/{positionUuid}', [StablecoinOperationsController::class, 'liquidatePosition']);
        });

        Route::get('/positions/{positionUuid}', [StablecoinOperationsController::class, 'getPositionDetails']);
    });
});
