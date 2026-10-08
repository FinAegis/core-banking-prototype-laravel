<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Interledger\ConnectionController;
use App\Http\Controllers\Api\Interledger\PaymentController;
use App\Http\Controllers\Api\Interledger\QuoteController;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

Route::prefix('v1/interledger')->middleware('auth:sanctum')->group(function (): void {
    // Reference data: the connector's supported assets (as the GraphQL
    // ilpSupportedAssets query).
    Route::get('/supported-assets', [QuoteController::class, 'supportedAssets']);

    // Quotes, payments and STREAM connections: platform operators only
    // (StaffRoles::OPERATORS), as for the GraphQL Interledger mutations. The
    // requests carry only external wallet addresses, so there is no local
    // account the caller could own.
    Route::middleware(RoleMiddleware::using(StaffRoles::OPERATORS))->group(function (): void {
        // Cross-currency quotes
        Route::post('/quotes', [QuoteController::class, 'store']);

        // Open Payments — incoming / outgoing payments
        Route::post('/payments/incoming', [PaymentController::class, 'createIncoming']);
        Route::post('/payments/outgoing', [PaymentController::class, 'createOutgoing']);
        Route::get('/payments/{id}', [PaymentController::class, 'status']);

        // ILP STREAM connections
        Route::post('/connections', [ConnectionController::class, 'store']);
        Route::delete('/connections/{id}', [ConnectionController::class, 'destroy']);
    });
});
