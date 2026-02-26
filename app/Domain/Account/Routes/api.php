<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AccountBalanceController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\CustodianController;
use App\Http\Controllers\Api\SubProductController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransactionReversalController;
use App\Http\Controllers\Api\TransferController;
use Illuminate\Support\Facades\Route;

// Core account, transaction, and transfer endpoints
Route::middleware('auth:sanctum', 'check.token.expiration')->group(function () {
    // Sub-product status for authenticated users
    Route::get('/sub-products/enabled', [SubProductController::class, 'enabled']);

    // Legacy accounts route for backward compatibility
    Route::get('/accounts', [AccountController::class, 'index'])->middleware('api.rate_limit:query');

    // Versioned routes for backward compatibility
    Route::prefix('v1')->middleware('api.rate_limit:query')->group(function () {
        Route::get('/accounts', [AccountController::class, 'index']);
    });

    Route::prefix('v2')->middleware('api.rate_limit:query')->group(function () {
        Route::get('/accounts', [AccountController::class, 'index']);
    });

    // Account management endpoints (query rate limiting)
    Route::middleware('api.rate_limit:query')->group(function () {
        Route::post('/accounts', [AccountController::class, 'store'])->middleware('scope:write');
        Route::get('/accounts/{uuid}', [AccountController::class, 'show'])->middleware('scope:read');
        Route::delete('/accounts/{uuid}', [AccountController::class, 'destroy'])->middleware('scope:delete');
        Route::post('/accounts/{uuid}/freeze', [AccountController::class, 'freeze'])->middleware('scope:write');
        Route::post('/accounts/{uuid}/unfreeze', [AccountController::class, 'unfreeze'])->middleware('scope:write');
        Route::get('/accounts/{uuid}/transactions', [TransactionController::class, 'history'])->middleware('scope:read');
    });

    // Transaction endpoints
    Route::post('/accounts/{uuid}/deposit', [TransactionController::class, 'deposit'])
        ->middleware(['transaction.rate_limit:deposit', 'idempotency', 'scope:write']);
    Route::post('/accounts/{uuid}/withdraw', [TransactionController::class, 'withdraw'])
        ->middleware(['transaction.rate_limit:withdraw', 'idempotency', 'scope:write']);

    // Transfer endpoints
    Route::post('/transfers', [TransferController::class, 'store'])
        ->middleware(['transaction.rate_limit:transfer', 'idempotency', 'scope:write']);
    Route::middleware('api.rate_limit:query')->group(function () {
        Route::get('/transfers/{uuid}', [TransferController::class, 'show']);
        Route::get('/accounts/{uuid}/transfers', [TransferController::class, 'history']);

        // Balance inquiry endpoints (legacy)
        Route::get('/accounts/{uuid}/balance', [BalanceController::class, 'show']);
        Route::get('/accounts/{uuid}/balance/summary', [BalanceController::class, 'summary']);

        // Multi-asset balance endpoints
        Route::get('/accounts/{uuid}/balances', [AccountBalanceController::class, 'show']);
        Route::get('/balances', [AccountBalanceController::class, 'index']);
    });

    // Currency conversion endpoint
    Route::post('/exchange/convert', [App\Http\Controllers\Api\ExchangeRateController::class, 'convertCurrency'])
        ->middleware(['transaction.rate_limit:convert', 'sub_product:exchange']);

    // Custodian integration endpoints
    Route::prefix('custodians')->group(function () {
        Route::get('/', [CustodianController::class, 'index']);
        Route::get('/{custodian}/account-info', [CustodianController::class, 'accountInfo']);
        Route::get('/{custodian}/balance', [CustodianController::class, 'balance']);
        Route::post('/{custodian}/transfer', [CustodianController::class, 'transfer']);
        Route::get('/{custodian}/transactions', [CustodianController::class, 'transactionHistory']);
        Route::get('/{custodian}/transactions/{transactionId}', [CustodianController::class, 'transactionStatus']);
    });

    // Transaction Reversal endpoints
    Route::post('/accounts/{uuid}/transactions/reverse', [TransactionReversalController::class, 'reverseTransaction']);
    Route::get('/accounts/{uuid}/transactions/reversals', [TransactionReversalController::class, 'getReversalHistory']);
    Route::get('/transactions/reversals/{reversalId}/status', [TransactionReversalController::class, 'getReversalStatus']);
});
