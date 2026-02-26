<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DeFi\DeFiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/defi')->name('api.defi.')->group(function () {
    Route::get('/protocols', [DeFiController::class, 'protocols'])->name('protocols');

    Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
        Route::post('/swap/quote', [DeFiController::class, 'swapQuote'])->name('swap.quote');
        Route::post('/swap/execute', [DeFiController::class, 'swapExecute'])
            ->middleware(['transaction.rate_limit:defi', 'idempotency'])
            ->name('swap.execute');
        Route::get('/lending/markets', [DeFiController::class, 'lendingMarkets'])->name('lending.markets');
        Route::get('/portfolio', [DeFiController::class, 'portfolio'])->name('portfolio');
        Route::get('/positions', [DeFiController::class, 'positions'])->name('positions');
        Route::get('/staking', [DeFiController::class, 'staking'])->name('staking');
        Route::get('/yield', [DeFiController::class, 'yield'])->name('yield');
    });
});
