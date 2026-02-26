<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CrossChain\CrossChainController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/crosschain')->name('api.crosschain.')->group(function () {
    Route::get('/chains', [CrossChainController::class, 'chains'])->name('chains');

    Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
        Route::post('/bridge/quote', [CrossChainController::class, 'bridgeQuote'])->name('bridge.quote');
        Route::post('/bridge/initiate', [CrossChainController::class, 'bridgeInitiate'])
            ->middleware(['transaction.rate_limit:crosschain', 'idempotency'])
            ->name('bridge.initiate');
        Route::get('/bridge/{id}/status', [CrossChainController::class, 'bridgeStatus'])->name('bridge.status');
        Route::post('/swap/quote', [CrossChainController::class, 'swapQuote'])->name('swap.quote');
        Route::post('/swap/execute', [CrossChainController::class, 'swapExecute'])
            ->middleware(['transaction.rate_limit:crosschain', 'idempotency'])
            ->name('swap.execute');
    });
});
