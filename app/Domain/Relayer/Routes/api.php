<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Relayer\MobileRelayerController;
use App\Http\Controllers\Api\Relayer\RelayerController;
use App\Http\Controllers\Api\Relayer\SmartAccountController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/relayer')->name('api.relayer.')->group(function () {
    // Public endpoint for supported networks
    Route::get('/networks', [RelayerController::class, 'networks'])->name('networks');

    // Authenticated endpoints
    Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
        Route::post('/sponsor', [RelayerController::class, 'sponsor'])
            ->middleware('transaction.rate_limit:relayer')
            ->name('sponsor');
        Route::post('/estimate', [RelayerController::class, 'estimate'])->name('estimate');

        // Smart Account Management (v2.6.0)
        Route::get('/account', [SmartAccountController::class, 'getAccount'])->name('account.show');
        Route::post('/account', [SmartAccountController::class, 'createAccount'])
            ->middleware('transaction.rate_limit:relayer')
            ->name('account.create');
        Route::get('/accounts', [SmartAccountController::class, 'listAccounts'])->name('accounts.list');
        Route::get('/nonce/{address}', [SmartAccountController::class, 'getNonce'])->name('nonce');
        Route::get('/init-code/{address}', [SmartAccountController::class, 'getInitCode'])->name('init-code');
    });
});

Route::prefix('v1/relayer')->name('mobile.relayer.')
    ->middleware(['auth:sanctum', 'check.token.expiration'])
    ->group(function () {
        Route::get('/status', [MobileRelayerController::class, 'status'])
            ->middleware('api.rate_limit:query')
            ->name('status');
        Route::post('/estimate-gas', [MobileRelayerController::class, 'estimateGas'])
            ->middleware('api.rate_limit:query')
            ->name('estimate-gas');
        // Alias: mobile expects GET /api/v1/relayer/estimate-fee
        Route::get('/estimate-fee', [MobileRelayerController::class, 'estimateGas'])
            ->middleware('api.rate_limit:query')
            ->name('estimate-fee');
        Route::post('/build-userop', [MobileRelayerController::class, 'buildUserOp'])
            ->middleware('api.rate_limit:query')
            ->name('build-userop');
        Route::post('/submit', [MobileRelayerController::class, 'submitUserOp'])
            ->middleware('transaction.rate_limit:relayer')
            ->name('submit');
        Route::get('/userop/{hash}', [MobileRelayerController::class, 'getUserOp'])
            ->middleware('api.rate_limit:query')
            ->name('userop');
        Route::get('/supported-tokens', [MobileRelayerController::class, 'supportedTokens'])
            ->middleware('api.rate_limit:query')
            ->name('supported-tokens');
        Route::get('/paymaster-data', [MobileRelayerController::class, 'paymasterData'])
            ->middleware('api.rate_limit:query')
            ->name('paymaster-data');
        Route::get('/networks/{network}/status', [MobileRelayerController::class, 'networkStatus'])
            ->middleware('api.rate_limit:query')
            ->name('network.status');
    });
