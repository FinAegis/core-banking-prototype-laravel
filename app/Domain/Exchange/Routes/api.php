<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\ExchangeRateController;
use Illuminate\Support\Facades\Route;

// Public asset and exchange rate endpoints
Route::middleware('api.rate_limit:public')->group(function () {
    Route::get('/exchange-rates', [ExchangeRateController::class, 'index']);
    Route::get('/exchange-rates/{from}/{to}', [ExchangeRateController::class, 'show']);
    Route::get('/exchange-rates/{from}/{to}/convert', [ExchangeRateController::class, 'convert']);

    // Sub-product status endpoints
    Route::prefix('sub-products')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\SubProductController::class, 'index']);
        Route::get('/{subProduct}', [App\Http\Controllers\Api\SubProductController::class, 'show']);
    });

    // Public settings endpoints
    Route::prefix('settings')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\SettingsController::class, 'index']);
        Route::get('/group/{group}', [App\Http\Controllers\Api\SettingsController::class, 'group']);
    });

    // Public status endpoint
    Route::get('/status', [App\Http\Controllers\StatusController::class, 'api'])->name('status.api');

    // Exchange endpoints
    Route::prefix('exchange')->name('api.exchange.')->group(function () {
        Route::get('/orderbook/{baseCurrency}/{quoteCurrency}', [App\Http\Controllers\Api\ExchangeController::class, 'getOrderBook'])->name('orderbook');
        Route::get('/markets', [App\Http\Controllers\Api\ExchangeController::class, 'getMarkets'])->name('markets');

        Route::middleware('auth:sanctum', 'check.token.expiration')->group(function () {
            Route::post('/orders', [App\Http\Controllers\Api\ExchangeController::class, 'placeOrder'])
                ->middleware(['transaction.rate_limit:exchange_order', 'idempotency'])
                ->name('orders.place');
            Route::delete('/orders/{orderId}', [App\Http\Controllers\Api\ExchangeController::class, 'cancelOrder'])->name('orders.cancel');
            Route::get('/orders', [App\Http\Controllers\Api\ExchangeController::class, 'getOrders'])->name('orders.index');
            Route::get('/trades', [App\Http\Controllers\Api\ExchangeController::class, 'getTrades'])->name('trades');
        });
    });

    // External Exchange endpoints
    Route::prefix('external-exchange')->name('api.external-exchange.')->group(function () {
        Route::get('/connectors', [App\Http\Controllers\Api\ExternalExchangeController::class, 'connectors'])->name('connectors');
        Route::get('/ticker/{base}/{quote}', [App\Http\Controllers\Api\ExternalExchangeController::class, 'ticker'])->name('ticker');
        Route::get('/orderbook/{base}/{quote}', [App\Http\Controllers\Api\ExternalExchangeController::class, 'orderBook'])->name('orderbook');

        Route::middleware('auth:sanctum', 'check.token.expiration')->group(function () {
            Route::get('/arbitrage/{base}/{quote}', [App\Http\Controllers\Api\ExternalExchangeController::class, 'arbitrage'])->name('arbitrage');
        });
    });

    // Liquidity Pool endpoints
    Route::prefix('liquidity')->name('api.liquidity.')->group(function () {
        Route::get('/pools', [App\Http\Controllers\Api\LiquidityPoolController::class, 'index'])->name('pools.index');
        Route::get('/pools/{poolId}', [App\Http\Controllers\Api\LiquidityPoolController::class, 'show'])->name('pools.show');

        Route::middleware('auth:sanctum', 'check.token.expiration')->group(function () {
            Route::post('/pools', [App\Http\Controllers\Api\LiquidityPoolController::class, 'create'])->middleware('idempotency')->name('pools.create');
            Route::post('/add', [App\Http\Controllers\Api\LiquidityPoolController::class, 'addLiquidity'])->middleware('idempotency')->name('add');
            Route::post('/remove', [App\Http\Controllers\Api\LiquidityPoolController::class, 'removeLiquidity'])->middleware('idempotency')->name('remove');
            Route::post('/swap', [App\Http\Controllers\Api\LiquidityPoolController::class, 'swap'])->middleware('idempotency')->name('swap');
            Route::get('/positions', [App\Http\Controllers\Api\LiquidityPoolController::class, 'positions'])->name('positions');
            Route::post('/claim-rewards', [App\Http\Controllers\Api\LiquidityPoolController::class, 'claimRewards'])->middleware('idempotency')->name('claim-rewards');

            // IL Protection endpoints
            Route::get('/il-protection/{positionId}', [App\Http\Controllers\Api\LiquidityPoolController::class, 'calculateImpermanentLoss'])->name('il-protection.calculate');
            Route::post('/il-protection/enable', [App\Http\Controllers\Api\LiquidityPoolController::class, 'enableImpermanentLossProtection'])->name('il-protection.enable');
            Route::post('/il-protection/process-claims', [App\Http\Controllers\Api\LiquidityPoolController::class, 'processImpermanentLossProtectionClaims'])->name('il-protection.process-claims');
            Route::get('/il-protection/fund-requirements/{poolId}', [App\Http\Controllers\Api\LiquidityPoolController::class, 'getImpermanentLossProtectionFundRequirements'])->name('il-protection.fund-requirements');

            // Analytics endpoints
            Route::get('/analytics/{poolId}', [App\Http\Controllers\Api\LiquidityPoolController::class, 'getPoolAnalytics'])->name('analytics');
        });
    });
});

// V1 Asset and exchange rate endpoints
Route::prefix('v1')->middleware('api.rate_limit:public')->group(function () {
    // Versioned accounts endpoint (requires authentication)
    Route::middleware('auth:sanctum', 'check.token.expiration')->get('/accounts', [App\Http\Controllers\Api\AccountController::class, 'index']);

    // Asset management endpoints
    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/{code}', [AssetController::class, 'show']);

    // Exchange rate endpoints (legacy v1 support)
    Route::get('/exchange-rates', [ExchangeRateController::class, 'index']);
    Route::get('/exchange-rates/{from}/{to}', [ExchangeRateController::class, 'show']);
    Route::get('/exchange-rates/{from}/{to}/convert', [ExchangeRateController::class, 'convert']);

    // Exchange rate provider endpoints
    Route::prefix('exchange-providers')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\ExchangeRateProviderController::class, 'index']);
        Route::get('/{provider}/rate', [App\Http\Controllers\Api\ExchangeRateProviderController::class, 'getRate']);
        Route::get('/compare', [App\Http\Controllers\Api\ExchangeRateProviderController::class, 'compareRates']);
        Route::get('/aggregated', [App\Http\Controllers\Api\ExchangeRateProviderController::class, 'getAggregatedRate']);
        Route::post('/refresh', [App\Http\Controllers\Api\ExchangeRateProviderController::class, 'refresh'])->middleware('auth:sanctum', 'check.token.expiration');
        Route::get('/historical', [App\Http\Controllers\Api\ExchangeRateProviderController::class, 'historical']);
        Route::post('/validate', [App\Http\Controllers\Api\ExchangeRateProviderController::class, 'validateRate']);
    });
});
