<?php

declare(strict_types=1);

use App\Http\Controllers\Api\LiquidityForecastController;
use App\Http\Controllers\Api\Treasury\PortfolioController;
use App\Http\Controllers\Api\YieldOptimizationController;
use Illuminate\Support\Facades\Route;

// Treasury Management endpoints
Route::prefix('treasury')->name('api.treasury.')->group(function () {
    // Authenticated treasury routes
    Route::middleware('auth:sanctum', 'check.token.expiration', 'scope:treasury')->group(function () {
        // Portfolio Management endpoints
        Route::prefix('portfolios')->name('portfolios.')->group(function () {
            Route::get('/', [PortfolioController::class, 'index'])->name('index');
            Route::post('/', [PortfolioController::class, 'store'])
                ->middleware(['transaction.rate_limit:treasury', 'idempotency'])
                ->name('store');
            Route::get('/{id}', [PortfolioController::class, 'show'])->name('show');
            Route::put('/{id}', [PortfolioController::class, 'update'])
                ->middleware(['transaction.rate_limit:treasury', 'idempotency'])
                ->name('update');
            Route::delete('/{id}', [PortfolioController::class, 'destroy'])
                ->middleware('transaction.rate_limit:treasury')
                ->name('destroy');

            // Asset allocation endpoints
            Route::post('/{id}/allocate', [PortfolioController::class, 'allocate'])
                ->middleware(['transaction.rate_limit:treasury', 'idempotency'])
                ->name('allocate');
            Route::get('/{id}/allocations', [PortfolioController::class, 'getAllocations'])->name('allocations');

            // Rebalancing endpoints
            Route::post('/{id}/rebalance', [PortfolioController::class, 'triggerRebalancing'])
                ->middleware(['transaction.rate_limit:treasury', 'idempotency'])
                ->name('rebalance');
            Route::get('/{id}/rebalancing-plan', [PortfolioController::class, 'getRebalancingPlan'])->name('rebalancing-plan');
            Route::post('/{id}/approve-rebalancing', [PortfolioController::class, 'approveRebalancing'])
                ->middleware(['transaction.rate_limit:treasury', 'idempotency'])
                ->name('approve-rebalancing');

            // Performance and analytics endpoints
            Route::get('/{id}/performance', [PortfolioController::class, 'getPerformance'])->name('performance');
            Route::get('/{id}/valuation', [PortfolioController::class, 'getValuation'])->name('valuation');
            Route::get('/{id}/history', [PortfolioController::class, 'getHistory'])->name('history');

            // Reporting endpoints
            Route::post('/{id}/reports', [PortfolioController::class, 'generateReport'])
                ->middleware('transaction.rate_limit:treasury')
                ->name('generate-report');
            Route::get('/{id}/reports', [PortfolioController::class, 'listReports'])->name('list-reports');
        });

        // Liquidity Forecasting endpoints
        Route::prefix('liquidity-forecast')->name('liquidity.')->group(function () {
            Route::post('/generate', [LiquidityForecastController::class, 'generateForecast'])->name('generate');
            Route::get('/{treasuryId}/current', [LiquidityForecastController::class, 'getCurrentLiquidity'])->name('current');
            Route::post('/workflow/start', [LiquidityForecastController::class, 'startForecastingWorkflow'])->name('workflow.start');
            Route::get('/{treasuryId}/alerts', [LiquidityForecastController::class, 'getAlerts'])->name('alerts');
        });

        // Yield Optimization endpoints
        Route::prefix('yield')->name('yield.')->group(function () {
            Route::post('/optimize', [YieldOptimizationController::class, 'optimizePortfolio'])->name('optimize');
            Route::get('/{treasuryId}/portfolio', [YieldOptimizationController::class, 'getPortfolio'])->name('portfolio');
        });
    });
});
