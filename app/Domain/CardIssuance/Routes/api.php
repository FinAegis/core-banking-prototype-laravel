<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Http\Controllers\FinCardWebhookController;
use App\Domain\CardIssuance\Http\Controllers\WaitlistDepositController;
use App\Domain\CardIssuance\Webhooks\CardWaitlistWebhookController;
use App\Http\Controllers\Api\CardIssuance\CardController;
use App\Http\Controllers\Api\CardIssuance\CardholderController;
use App\Http\Controllers\Api\CardIssuance\CardTransactionWebhookController;
use App\Http\Controllers\Api\CardIssuance\FinCardAccountController;
use App\Http\Controllers\Api\CardIssuance\FinCardCardController;
use App\Http\Controllers\Api\CardIssuance\FinCardDevController;
use App\Http\Controllers\Api\CardIssuance\FinCardOnboardingController;
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

// FinCard cardholder onboarding + KYC (Phase 2). Registered BEFORE the generic
// v1/cards group so these specific paths are not swallowed by the greedy
// /v1/cards/{cardId} route. No require.kyc here — these endpoints START the
// FinCard KYC flow (mirrors the Bridge KYC-setup routes); card creation gates
// on a verified cardholder later.
Route::prefix('v1/cards')->name('api.cards.fincard.')
    ->middleware(['auth:sanctum'])
    ->group(function (): void {
        Route::get('/onboarding', [FinCardOnboardingController::class, 'onboarding'])->name('onboarding');
        Route::get('/reference/card-types', [FinCardOnboardingController::class, 'cardTypes'])->name('reference.card-types');
        Route::get('/cardholder', [FinCardOnboardingController::class, 'status'])->name('cardholder.status');
        Route::post('/kyc/documents', [FinCardOnboardingController::class, 'uploadDocument'])
            ->middleware('api.rate_limit:mutation')
            ->name('kyc.documents');
        Route::post('/cardholder', [FinCardOnboardingController::class, 'createCardholder'])
            ->middleware(['idempotency.required', 'api.rate_limit:mutation'])
            ->name('cardholder.create');

        // Funding (Phase 3) — account balance, deposit coins, crypto address.
        Route::get('/account', [FinCardAccountController::class, 'account'])->name('account');
        Route::get('/account/coins', [FinCardAccountController::class, 'coins'])->name('account.coins');
        Route::post('/account/deposit-address', [FinCardAccountController::class, 'depositAddress'])
            ->middleware(['idempotency.required', 'api.rate_limit:mutation'])
            ->name('account.deposit-address');

        // DEV/QA ONLY — simulate an inbound deposit to exercise the funding UI
        // without a chain deposit or FinCard creds. Registered in non-production
        // ONLY; additionally gated behind FINCARD_DEV_SIMULATE_ENABLED in the
        // controller. Credits the LOCAL balance mirror + fires the same
        // fincard.account.funded broadcast a real webhook would.
        if (! app()->environment('production')) {
            Route::post('/dev/simulate-deposit', [FinCardDevController::class, 'simulateDeposit'])
                ->middleware(['idempotency.required', 'api.rate_limit:mutation'])
                ->name('dev.simulate-deposit');
        }

        // Card lifecycle (Phase 4) — namespaced under /fincard/* to avoid the
        // generic card routes. Sensitive PAN/CVV is fetched on demand, never stored.
        Route::prefix('/fincard')->name('cards.')->group(function (): void {
            Route::get('/', [FinCardCardController::class, 'index'])->name('index');
            Route::post('/open', [FinCardCardController::class, 'open'])
                ->middleware(['idempotency.required', 'api.rate_limit:mutation'])->name('open');
            Route::get('/{cardId}', [FinCardCardController::class, 'show'])->name('show');
            Route::get('/{cardId}/sensitive', [FinCardCardController::class, 'sensitive'])->name('sensitive');
            Route::get('/{cardId}/transactions', [FinCardCardController::class, 'transactions'])->name('transactions');
            Route::middleware(['idempotency.required', 'api.rate_limit:mutation'])->group(function (): void {
                Route::post('/{cardId}/freeze', [FinCardCardController::class, 'freeze'])->name('freeze');
                Route::post('/{cardId}/unfreeze', [FinCardCardController::class, 'unfreeze'])->name('unfreeze');
                Route::post('/{cardId}/cancel', [FinCardCardController::class, 'cancel'])->name('cancel');
                Route::post('/{cardId}/topup', [FinCardCardController::class, 'topUp'])->name('topup');
                Route::post('/{cardId}/withdraw', [FinCardCardController::class, 'withdraw'])->name('withdraw');
            });
        });
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

// FinCard (FinHub) platform webhook — single endpoint for card lifecycle,
// authorization, 3DS, cardholder-KYC, work-order and crypto-wallet events.
// Authenticated by RSA signature inside the controller (not Sanctum); FinCard
// retries on non-success, so processing is idempotent via
// processed_webhook_events (provider='fincard').
Route::post('v1/webhooks/fincard', [FinCardWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.v1.webhooks.fincard');

// Card issuer webhook endpoints (CRITICAL: <2000ms latency budget)
Route::prefix('webhooks/card-issuer')->name('api.webhooks.card.')
    ->middleware(['api.rate_limit:webhook', 'webhook.signature:marqeta'])
    ->group(function () {
        Route::post('/authorization', [JitFundingWebhookController::class, 'handleAuthorization'])->name('authorization');
        Route::post('/settlement', [JitFundingWebhookController::class, 'settlement'])->name('settlement');
        Route::post('/transaction', [CardTransactionWebhookController::class, 'handleTransaction'])->name('transaction');
    });
