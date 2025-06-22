<?php

use App\Http\Controllers\Api\V2\PublicApiController;
use App\Http\Controllers\Api\V2\WebhookController;
use App\Http\Controllers\Api\V2\GCUController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V2 Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for version 2 of the public API.
| These routes are designed for external developers and third-party integrations.
|
*/

// Public API information endpoints (no authentication required)
Route::get('/', [PublicApiController::class, 'index']);
Route::get('/status', [PublicApiController::class, 'status']);

// GCU-specific endpoints (public read access)
Route::prefix('gcu')->group(function () {
    Route::get('/', [GCUController::class, 'index']);
    Route::get('/value-history', [GCUController::class, 'valueHistory']);
    Route::get('/governance/active-polls', [GCUController::class, 'activePolls']);
    Route::get('/supported-banks', [GCUController::class, 'supportedBanks']);
});

// Webhook event types (public information)
Route::get('/webhooks/events', [WebhookController::class, 'events']);

// Public basket endpoints (read-only)
Route::prefix('baskets')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\BasketController::class, 'index']);
    Route::get('/{code}', [\App\Http\Controllers\Api\BasketController::class, 'show']);
    Route::get('/{code}/value', [\App\Http\Controllers\Api\BasketController::class, 'getValue']);
    Route::get('/{code}/history', [\App\Http\Controllers\Api\BasketController::class, 'getHistory']);
    Route::get('/{code}/performance', [\App\Http\Controllers\Api\BasketController::class, 'getPerformance']);
});

// Authenticated endpoints
Route::middleware('auth:sanctum')->group(function () {
    // Webhook management
    Route::prefix('webhooks')->group(function () {
        Route::get('/', [WebhookController::class, 'index']);
        Route::post('/', [WebhookController::class, 'store']);
        Route::get('/{id}', [WebhookController::class, 'show']);
        Route::put('/{id}', [WebhookController::class, 'update']);
        Route::delete('/{id}', [WebhookController::class, 'destroy']);
        Route::get('/{id}/deliveries', [WebhookController::class, 'deliveries']);
    });

    // Include existing V2 endpoints from main api.php
    Route::prefix('accounts')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AccountController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\AccountController::class, 'store']);
        Route::get('/{uuid}', [\App\Http\Controllers\Api\AccountController::class, 'show']);
        Route::delete('/{uuid}', [\App\Http\Controllers\Api\AccountController::class, 'destroy']);
        Route::post('/{uuid}/freeze', [\App\Http\Controllers\Api\AccountController::class, 'freeze']);
        Route::post('/{uuid}/unfreeze', [\App\Http\Controllers\Api\AccountController::class, 'unfreeze']);
        
        // Multi-asset operations
        Route::get('/{uuid}/balances', [\App\Http\Controllers\Api\AccountBalanceController::class, 'show']);
        Route::post('/{uuid}/deposit', [\App\Http\Controllers\Api\TransactionController::class, 'deposit']);
        Route::post('/{uuid}/withdraw', [\App\Http\Controllers\Api\TransactionController::class, 'withdraw']);
        Route::get('/{uuid}/transactions', [\App\Http\Controllers\Api\TransactionController::class, 'history']);
        Route::get('/{uuid}/transfers', [\App\Http\Controllers\Api\TransferController::class, 'history']);
        
        // Basket operations
        Route::prefix('{uuid}/baskets')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\BasketAccountController::class, 'getBasketHoldings']);
            Route::post('/decompose', [\App\Http\Controllers\Api\BasketAccountController::class, 'decompose']);
            Route::post('/compose', [\App\Http\Controllers\Api\BasketAccountController::class, 'compose']);
        });
    });

    // Asset management
    Route::prefix('assets')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AssetController::class, 'index']);
        Route::get('/{code}', [\App\Http\Controllers\Api\AssetController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\AssetController::class, 'store']);
        Route::put('/{code}', [\App\Http\Controllers\Api\AssetController::class, 'update']);
        Route::delete('/{code}', [\App\Http\Controllers\Api\AssetController::class, 'destroy']);
    });

    // Exchange rates
    Route::prefix('exchange-rates')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ExchangeRateController::class, 'index']);
        Route::get('/{from}/{to}', [\App\Http\Controllers\Api\ExchangeRateController::class, 'show']);
        Route::get('/{from}/{to}/convert', [\App\Http\Controllers\Api\ExchangeRateController::class, 'convert']);
        Route::post('/refresh', [\App\Http\Controllers\Api\ExchangeRateController::class, 'refresh']);
    });

    // Transfers
    Route::prefix('transfers')->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\TransferController::class, 'store']);
        Route::get('/{uuid}', [\App\Http\Controllers\Api\TransferController::class, 'show']);
    });

    // Basket assets (protected operations)
    Route::prefix('baskets')->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\BasketController::class, 'store']);
        Route::post('/{code}/rebalance', [\App\Http\Controllers\Api\BasketController::class, 'rebalance']);
    });

    // Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\TransactionController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\TransactionController::class, 'show']);
    });
});