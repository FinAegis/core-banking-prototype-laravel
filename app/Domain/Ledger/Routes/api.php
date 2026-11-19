<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Ledger\LedgerController;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

Route::middleware('auth:sanctum')->prefix('v1/ledger')->group(function (): void {
    // The general ledger (reads and writes) is kept by platform operators
    // (StaffRoles::OPERATORS), as for the GraphQL ledger queries and mutations.
    Route::middleware(RoleMiddleware::using(StaffRoles::OPERATORS))->group(function (): void {
        // Chart of Accounts
        Route::get('/accounts', [LedgerController::class, 'accounts']);
        Route::get('/accounts/{code}/balance', [LedgerController::class, 'balance']);
        Route::get('/accounts/{code}/history', [LedgerController::class, 'history']);

        Route::post('/accounts', [LedgerController::class, 'createAccount']);

        // Journal Entries
        Route::post('/entries', [LedgerController::class, 'postEntry']);
        Route::post('/entries/{id}/reverse', [LedgerController::class, 'reverseEntry']);

        // Reports
        Route::get('/trial-balance', [LedgerController::class, 'trialBalance']);
        Route::get('/reconciliation/{domain}', [LedgerController::class, 'reconciliation']);
    });
});
