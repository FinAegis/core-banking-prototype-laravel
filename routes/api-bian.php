<?php

use App\Http\Controllers\Api\BIAN\CurrentAccountController;
use App\Http\Controllers\Api\BIAN\PaymentInitiationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BIAN-Compliant API Routes
|--------------------------------------------------------------------------
|
| These routes follow BIAN (Banking Industry Architecture Network) standards
| for service domains, control records, and behavior qualifiers.
|
*/

// Demo BIAN surface: moves internal ledger balances directly, so it follows
// the ledger.direct_funding_enabled flag (404 when disabled).
Route::middleware(['feature:ledger.direct_funding_enabled', 'auth:sanctum'])->prefix('bian')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Current Account Service Domain
    |--------------------------------------------------------------------------
    | Functional Pattern: Fulfill
    | Asset Type: Current Account Fulfillment Arrangement
    */

    // Control Record Operations
    Route::prefix('current-account')->group(function () {
        Route::post('initiate', [CurrentAccountController::class, 'initiate']);
        Route::get('{crReferenceId}/retrieve', [CurrentAccountController::class, 'retrieve']);
        Route::put('{crReferenceId}/update', [CurrentAccountController::class, 'update']);
        Route::put('{crReferenceId}/control', [CurrentAccountController::class, 'control']);

        // Behavior Qualifier: Payment
        Route::post('{crReferenceId}/payment/execute', [CurrentAccountController::class, 'executePayment']);

        // Behavior Qualifier: Deposit
        Route::post('{crReferenceId}/deposit/execute', [CurrentAccountController::class, 'executeDeposit']);

        // Behavior Qualifier: Account Balance
        Route::get('{crReferenceId}/account-balance/retrieve', [CurrentAccountController::class, 'retrieveAccountBalance']);

        // Behavior Qualifier: Transaction Report
        Route::get('{crReferenceId}/transaction-report/retrieve', [CurrentAccountController::class, 'retrieveTransactionReport']);
    });

    /*
    |--------------------------------------------------------------------------
    | Payment Initiation Service Domain
    |--------------------------------------------------------------------------
    | Functional Pattern: Transact
    | Asset Type: Payment Transaction
    */

    // Control Record Operations
    Route::prefix('payment-initiation')->group(function () {
        Route::post('initiate', [PaymentInitiationController::class, 'initiate']);
        Route::get('{crReferenceId}/retrieve', [PaymentInitiationController::class, 'retrieve']);
        Route::put('{crReferenceId}/update', [PaymentInitiationController::class, 'update']);
        Route::post('{crReferenceId}/execute', [PaymentInitiationController::class, 'execute']);

        // Behavior Qualifier: Payment Status
        Route::post('{crReferenceId}/payment-status/request', [PaymentInitiationController::class, 'requestPaymentStatus']);

        // Behavior Qualifier: Payment History
        Route::get('{accountReference}/payment-history/retrieve', [PaymentInitiationController::class, 'retrievePaymentHistory']);
    });
});
