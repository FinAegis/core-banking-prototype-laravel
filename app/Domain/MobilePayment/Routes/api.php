<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MobilePayment\ActivityController;
use App\Http\Controllers\Api\MobilePayment\NetworkStatusController;
use App\Http\Controllers\Api\MobilePayment\PaymentIntentController;
use App\Http\Controllers\Api\MobilePayment\ReceiptController;
use App\Http\Controllers\Api\MobilePayment\TransactionController as MobileTransactionController;
use App\Http\Controllers\Api\MobilePayment\WalletReceiveController;
use App\Http\Controllers\Api\Wallet\WalletTransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
    // Payment Intents
    Route::post('/payments/intents', [PaymentIntentController::class, 'create'])
        ->middleware(['transaction.rate_limit:payment_intent', 'idempotency'])
        ->name('mobile.payments.intents.create');
    Route::get('/payments/intents/{intentId}', [PaymentIntentController::class, 'show'])
        ->middleware('api.rate_limit:query')
        ->name('mobile.payments.intents.show');
    Route::post('/payments/intents/{intentId}/submit', [PaymentIntentController::class, 'submit'])
        ->middleware(['transaction.rate_limit:payment_submit', 'idempotency'])
        ->name('mobile.payments.intents.submit');
    Route::post('/payments/intents/{intentId}/cancel', [PaymentIntentController::class, 'cancel'])
        ->middleware(['transaction.rate_limit:payment_submit', 'idempotency'])
        ->name('mobile.payments.intents.cancel');

    // Activity Feed
    Route::get('/activity', [ActivityController::class, 'index'])
        ->middleware('api.rate_limit:query')
        ->name('mobile.activity.index');

    // Transaction Details
    Route::get('/transactions/{txId}', [MobileTransactionController::class, 'show'])
        ->middleware('api.rate_limit:query')
        ->name('mobile.transactions.show');

    // Receipt Generation
    Route::post('/transactions/{txId}/receipt', [ReceiptController::class, 'store'])
        ->middleware('api.rate_limit:mutation')
        ->name('mobile.transactions.receipt');
    Route::get('/transactions/{txId}/receipt', [ReceiptController::class, 'store'])
        ->middleware('api.rate_limit:query')
        ->name('mobile.transactions.receipt.get');

    // Wallet Receive Address
    Route::get('/wallet/receive', WalletReceiveController::class)
        ->middleware('api.rate_limit:query')
        ->name('mobile.wallet.receive');

    // Network Status
    Route::get('/networks/status', NetworkStatusController::class)
        ->middleware('api.rate_limit:query')
        ->name('mobile.networks.status');
    Route::get('/networks/{network}/status', NetworkStatusController::class)
        ->middleware('api.rate_limit:query')
        ->name('mobile.networks.status.parameterized');

    // Wallet Transfer Helpers (P2P send flow)
    Route::get('/wallet/validate-address', [WalletTransferController::class, 'validateAddress'])
        ->middleware('api.rate_limit:query')
        ->name('mobile.wallet.validate-address');
    Route::post('/wallet/resolve-name', [WalletTransferController::class, 'resolveName'])
        ->middleware('api.rate_limit:query')
        ->name('mobile.wallet.resolve-name');
    Route::post('/wallet/quote', [WalletTransferController::class, 'quote'])
        ->middleware('api.rate_limit:query')
        ->name('mobile.wallet.quote');

    // Transaction quote with recipient address validation
    Route::post('/wallet/transactions/quote', [WalletTransferController::class, 'transactionQuote'])
        ->middleware('api.rate_limit:query')
        ->name('mobile.wallet.transactions.quote');
});
