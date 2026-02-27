<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CardIssuance\CardController;
use App\Http\Controllers\Api\CardIssuance\JitFundingWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/cards')->name('api.cards.')->group(function () {
    // Authenticated endpoints
    Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
        Route::get('/', [CardController::class, 'index'])->name('index');
        Route::post('/', [CardController::class, 'store'])
            ->middleware('transaction.rate_limit:card_provision')
            ->name('store');
        Route::post('/provision', [CardController::class, 'provision'])
            ->middleware('transaction.rate_limit:card_provision')
            ->name('provision');
        Route::get('/{cardId}', [CardController::class, 'show'])->name('show');
        Route::get('/{cardId}/transactions', [CardController::class, 'transactions'])->name('transactions');
        Route::post('/{cardId}/freeze', [CardController::class, 'freeze'])->name('freeze');
        Route::delete('/{cardId}/freeze', [CardController::class, 'unfreeze'])->name('unfreeze');
        Route::delete('/{cardId}', [CardController::class, 'cancel'])->name('cancel');
    });
});

// Card issuer webhook endpoints (CRITICAL: <2000ms latency budget)
Route::prefix('webhooks/card-issuer')->name('api.webhooks.card.')
    ->middleware(['api.rate_limit:webhook', 'webhook.signature:marqeta'])
    ->group(function () {
        Route::post('/authorization', [JitFundingWebhookController::class, 'handleAuthorization'])->name('authorization');
        Route::post('/settlement', [JitFundingWebhookController::class, 'settlement'])->name('settlement');
    });
