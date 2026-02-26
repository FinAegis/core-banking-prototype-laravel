<?php

declare(strict_types=1);

use App\Http\Controllers\Api\LoanApplicationController;
use App\Http\Controllers\Api\LoanController;
use Illuminate\Support\Facades\Route;

// P2P Lending endpoints
Route::prefix('lending')->middleware(['auth:sanctum', 'check.token.expiration', 'sub_product:lending'])->group(function () {
    // Loan applications
    Route::middleware('api.rate_limit:query')->group(function () {
        Route::get('/applications', [LoanApplicationController::class, 'index']);
        Route::get('/applications/{id}', [LoanApplicationController::class, 'show']);
    });

    Route::middleware(['transaction.rate_limit:lending', 'idempotency'])->group(function () {
        Route::post('/applications', [LoanApplicationController::class, 'store']);
        Route::post('/applications/{id}/cancel', [LoanApplicationController::class, 'cancel']);
    });

    // Loans
    Route::middleware('api.rate_limit:query')->group(function () {
        Route::get('/loans', [LoanController::class, 'index']);
        Route::get('/loans/{id}', [LoanController::class, 'show']);
        Route::get('/loans/{id}/settlement-quote', [LoanController::class, 'settleEarly']);
    });

    Route::middleware(['transaction.rate_limit:lending', 'idempotency'])->group(function () {
        Route::post('/loans/{id}/payments', [LoanController::class, 'makePayment']);
        Route::post('/loans/{id}/settle', [LoanController::class, 'confirmSettlement'])->name('api.loans.confirm-settlement');
    });
});
