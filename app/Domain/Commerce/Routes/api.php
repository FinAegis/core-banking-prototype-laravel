<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Commerce\MobileCommerceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/commerce')->name('mobile.commerce.')
    ->middleware(['auth:sanctum', 'check.token.expiration'])
    ->group(function () {
        Route::get('/merchants', [MobileCommerceController::class, 'merchants'])
            ->middleware('api.rate_limit:query')
            ->name('merchants');
        Route::post('/parse-qr', [MobileCommerceController::class, 'parseQr'])
            ->middleware('api.rate_limit:query')
            ->name('parse-qr');
        Route::post('/payment-requests', [MobileCommerceController::class, 'createPaymentRequest'])
            ->middleware(['transaction.rate_limit:payment_intent', 'idempotency'])
            ->name('payment-requests');
        Route::post('/payments', [MobileCommerceController::class, 'processPayment'])
            ->middleware(['transaction.rate_limit:payment_intent', 'idempotency'])
            ->name('payments');
        Route::post('/generate-qr', [MobileCommerceController::class, 'generateQr'])
            ->middleware('api.rate_limit:query')
            ->name('generate-qr');

        // Merchant detail
        Route::get('/merchants/{merchantId}', [MobileCommerceController::class, 'merchantDetail'])
            ->middleware('api.rate_limit:query')
            ->name('merchants.detail');

        // Payment request detail & cancel
        Route::get('/payment-requests/{paymentId}', [MobileCommerceController::class, 'paymentRequestDetail'])
            ->middleware('api.rate_limit:query')
            ->name('payment-requests.detail');
        Route::post('/payment-requests/{paymentId}/cancel', [MobileCommerceController::class, 'cancelPaymentRequest'])
            ->middleware('transaction.rate_limit:payment_intent')
            ->name('payment-requests.cancel');

        // Recent payments
        Route::get('/payments/recent', [MobileCommerceController::class, 'recentPayments'])
            ->middleware('api.rate_limit:query')
            ->name('payments.recent');
    });
