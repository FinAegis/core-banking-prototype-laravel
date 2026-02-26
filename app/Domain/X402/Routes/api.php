<?php

declare(strict_types=1);

use App\Http\Controllers\Api\X402\X402EndpointController;
use App\Http\Controllers\Api\X402\X402PaymentController;
use App\Http\Controllers\Api\X402\X402SpendingLimitController;
use App\Http\Controllers\Api\X402\X402StatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/x402')->name('api.x402.')->group(function () {
    // Public endpoints
    Route::get('/status', [X402StatusController::class, 'status'])->name('status');
    Route::get('/supported', [X402StatusController::class, 'supported'])->name('supported');

    // Authenticated endpoints
    Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
        // Monetized endpoint management
        Route::get('/endpoints', [X402EndpointController::class, 'index'])->name('endpoints.index');
        Route::post('/endpoints', [X402EndpointController::class, 'store'])
            ->middleware(['transaction.rate_limit:x402', 'idempotency'])
            ->name('endpoints.store');
        Route::get('/endpoints/{id}', [X402EndpointController::class, 'show'])->name('endpoints.show');
        Route::put('/endpoints/{id}', [X402EndpointController::class, 'update'])
            ->middleware(['transaction.rate_limit:x402', 'idempotency'])
            ->name('endpoints.update');
        Route::delete('/endpoints/{id}', [X402EndpointController::class, 'destroy'])
            ->middleware('transaction.rate_limit:x402')
            ->name('endpoints.destroy');

        // Payment history and details (stats before {id} wildcard)
        Route::get('/payments', [X402PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/stats', [X402PaymentController::class, 'stats'])->name('payments.stats');
        Route::get('/payments/{id}', [X402PaymentController::class, 'show'])->name('payments.show');

        // Agent spending limits
        Route::get('/spending-limits', [X402SpendingLimitController::class, 'index'])->name('spending-limits.index');
        Route::post('/spending-limits', [X402SpendingLimitController::class, 'store'])
            ->middleware(['transaction.rate_limit:x402', 'idempotency'])
            ->name('spending-limits.store');
        Route::get('/spending-limits/{agentId}', [X402SpendingLimitController::class, 'show'])->name('spending-limits.show');
        Route::put('/spending-limits/{agentId}', [X402SpendingLimitController::class, 'update'])
            ->middleware(['transaction.rate_limit:x402', 'idempotency'])
            ->name('spending-limits.update');
        Route::delete('/spending-limits/{agentId}', [X402SpendingLimitController::class, 'destroy'])
            ->middleware('transaction.rate_limit:x402')
            ->name('spending-limits.destroy');
    });
});
