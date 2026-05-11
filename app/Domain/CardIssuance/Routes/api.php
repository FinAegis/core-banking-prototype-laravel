<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Http\Controllers\WaitlistDepositController;
use App\Domain\CardIssuance\Webhooks\CardWaitlistWebhookController;
use App\Http\Controllers\Api\CardIssuance\CardController;
use App\Http\Controllers\Api\CardIssuance\CardholderController;
use App\Http\Controllers\Api\CardIssuance\CardTransactionWebhookController;
use App\Http\Controllers\Api\CardIssuance\JitFundingWebhookController;
use Illuminate\Support\Facades\Route;

// Plan B Slice 5 — Card waitlist deposit endpoints.
// /waitlist/deposit + /waitlist/deposit/cancel are protected by Sanctum +
// idempotency.required (Idempotency-Key header). /waitlist/entry is a
// read-only Sanctum endpoint (no idempotency).
Route::prefix('v1/cards/waitlist')->name('api.cards.waitlist.')
    ->middleware(['auth:sanctum'])
    ->group(function (): void {
        Route::middleware(['idempotency.required'])->group(function (): void {
            Route::post('/deposit', [WaitlistDepositController::class, 'start'])
                ->name('deposit.start');
            Route::post('/deposit/cancel', [WaitlistDepositController::class, 'cancel'])
                ->name('deposit.cancel');
        });

        Route::get('/entry', [WaitlistDepositController::class, 'entry'])
            ->name('entry');
    });

Route::prefix('v1/cards')->name('api.cards.')->group(function () {
    // Authenticated endpoints
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [CardController::class, 'index'])->name('index');
        Route::post('/', [CardController::class, 'store'])
            ->middleware('transaction.rate_limit:card_provision')
            ->name('store');
        Route::post('/provision', [CardController::class, 'provision'])
            ->middleware('transaction.rate_limit:card_provision')
            ->name('provision');
        Route::get('/{cardId}', [CardController::class, 'show'])->name('show');
        Route::patch('/{cardId}', [CardController::class, 'update'])->name('update');
        Route::get('/{cardId}/transactions', [CardController::class, 'transactions'])->name('transactions');
        Route::post('/{cardId}/freeze', [CardController::class, 'freeze'])->name('freeze');
        Route::delete('/{cardId}/freeze', [CardController::class, 'unfreeze'])->name('unfreeze');
        Route::delete('/{cardId}', [CardController::class, 'cancel'])->name('cancel');
    });
});

// Plan B Slice 5 — Stripe Checkout webhook for card deposits. Signature
// verified inside the controller via STRIPE_CARDS_WEBHOOK_SECRET (distinct
// from /webhooks/stripe/subscriptions). Dedup via processed_webhook_events
// (provider='stripe_cards').
Route::post('webhooks/stripe/cards', [CardWaitlistWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.webhooks.stripe.cards');

// Cardholder management
Route::prefix('v1/cardholders')->name('api.cardholders.')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [CardholderController::class, 'index'])->name('index');
    Route::post('/', [CardholderController::class, 'store'])->name('store');
    Route::get('/{id}', [CardholderController::class, 'show'])->name('show');
});

// Card issuer webhook endpoints (CRITICAL: <2000ms latency budget)
Route::prefix('webhooks/card-issuer')->name('api.webhooks.card.')
    ->middleware(['api.rate_limit:webhook', 'webhook.signature:marqeta'])
    ->group(function () {
        Route::post('/authorization', [JitFundingWebhookController::class, 'handleAuthorization'])->name('authorization');
        Route::post('/settlement', [JitFundingWebhookController::class, 'settlement'])->name('settlement');
        Route::post('/transaction', [CardTransactionWebhookController::class, 'handleTransaction'])->name('transaction');
    });
