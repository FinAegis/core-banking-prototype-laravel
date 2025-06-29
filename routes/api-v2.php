<?php

use App\Http\Controllers\Api\V2\PublicApiController;
use App\Http\Controllers\Api\V2\WebhookController;
use App\Http\Controllers\Api\V2\GCUController;
use App\Http\Controllers\Api\V2\FinancialInstitutionController;
use App\Http\Controllers\Api\V2\ComplianceController;
use App\Http\Controllers\Api\V2\BankIntegrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V2 Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for version 2 of the public API.
| These routes are designed for external developers and third-party integrations.
|
*/

// Public API information endpoints (no authentication required)
Route::get('/', [PublicApiController::class, 'index']);
Route::get('/status', [PublicApiController::class, 'status']);

// Authentication endpoints (public)
Route::prefix('auth')->middleware('api.rate_limit:auth')->group(function () {
    Route::post('/register', [\App\Http\Controllers\Api\Auth\RegisterController::class, 'register']);
    Route::post('/login', [\App\Http\Controllers\Api\Auth\LoginController::class, 'login']);
    
    // Protected auth endpoints
    Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Api\Auth\LoginController::class, 'logout']);
        Route::post('/logout-all', [\App\Http\Controllers\Api\Auth\LoginController::class, 'logoutAll']);
        Route::post('/refresh', [\App\Http\Controllers\Api\Auth\LoginController::class, 'refresh']);
        Route::get('/user', [\App\Http\Controllers\Api\Auth\LoginController::class, 'user']);
    });
});

// GCU-specific endpoints (public read access)
Route::prefix('gcu')->group(function () {
    Route::get('/', [GCUController::class, 'index']);
    Route::get('/composition', [GCUController::class, 'composition']);
    Route::get('/value-history', [GCUController::class, 'valueHistory']);
    Route::get('/governance/active-polls', [GCUController::class, 'activePolls']);
    Route::get('/supported-banks', [GCUController::class, 'supportedBanks']);
    
    // Voting endpoints
    Route::prefix('voting')->group(function () {
        Route::get('/proposals', [\App\Http\Controllers\Api\V2\VotingController::class, 'proposals']);
        Route::get('/proposals/{id}', [\App\Http\Controllers\Api\V2\VotingController::class, 'proposalDetails']);
        
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/proposals/{id}/vote', [\App\Http\Controllers\Api\V2\VotingController::class, 'vote']);
            Route::get('/my-votes', [\App\Http\Controllers\Api\V2\VotingController::class, 'myVotes']);
        });
    });
});

// Webhook event types (public information)
Route::get('/webhooks/events', [WebhookController::class, 'events']);

// Financial Institution Onboarding (public endpoints)
Route::prefix('financial-institutions')->group(function () {
    Route::get('/application-form', [FinancialInstitutionController::class, 'getApplicationForm']);
    Route::post('/apply', [FinancialInstitutionController::class, 'submitApplication']);
    Route::get('/application/{applicationNumber}/status', [FinancialInstitutionController::class, 'getApplicationStatus']);
    Route::post('/application/{applicationNumber}/documents', [FinancialInstitutionController::class, 'uploadDocument']);
    Route::get('/api-documentation', [FinancialInstitutionController::class, 'getApiDocumentation']);
});

// Public basket endpoints (read-only)
Route::prefix('baskets')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\BasketController::class, 'index']);
    Route::get('/{code}', [\App\Http\Controllers\Api\BasketController::class, 'show']);
    Route::get('/{code}/value', [\App\Http\Controllers\Api\BasketController::class, 'getValue']);
    Route::get('/{code}/history', [\App\Http\Controllers\Api\BasketController::class, 'getHistory']);
    Route::get('/{code}/performance', [\App\Http\Controllers\Api\BasketController::class, 'getPerformance']);
});

// Authenticated endpoints (supports both Sanctum and API Key authentication)
Route::middleware(['auth.api_or_sanctum:read'])->group(function () {
    // Webhook management
    Route::prefix('webhooks')->group(function () {
        Route::get('/', [WebhookController::class, 'index']);
        Route::post('/', [WebhookController::class, 'store'])->middleware('auth.api_or_sanctum:write');
        Route::get('/{id}', [WebhookController::class, 'show']);
        Route::put('/{id}', [WebhookController::class, 'update'])->middleware('auth.api_or_sanctum:write');
        Route::delete('/{id}', [WebhookController::class, 'destroy'])->middleware('auth.api_or_sanctum:delete');
        Route::get('/{id}/deliveries', [WebhookController::class, 'deliveries']);
    });

    // Include existing V2 endpoints from main api.php
    Route::prefix('accounts')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AccountController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\AccountController::class, 'store'])->middleware('auth.api_or_sanctum:write');
        Route::get('/{uuid}', [\App\Http\Controllers\Api\AccountController::class, 'show']);
        Route::delete('/{uuid}', [\App\Http\Controllers\Api\AccountController::class, 'destroy'])->middleware('auth.api_or_sanctum:delete');
        Route::post('/{uuid}/freeze', [\App\Http\Controllers\Api\AccountController::class, 'freeze'])->middleware('auth.api_or_sanctum:write');
        Route::post('/{uuid}/unfreeze', [\App\Http\Controllers\Api\AccountController::class, 'unfreeze'])->middleware('auth.api_or_sanctum:write');
        
        // Multi-asset operations
        Route::get('/{uuid}/balances', [\App\Http\Controllers\Api\AccountBalanceController::class, 'show']);
        Route::post('/{uuid}/deposit', [\App\Http\Controllers\Api\TransactionController::class, 'deposit'])->middleware('auth.api_or_sanctum:write');
        Route::post('/{uuid}/withdraw', [\App\Http\Controllers\Api\TransactionController::class, 'withdraw'])->middleware('auth.api_or_sanctum:write');
        Route::get('/{uuid}/transactions', [\App\Http\Controllers\Api\TransactionController::class, 'history']);
        Route::get('/{uuid}/transfers', [\App\Http\Controllers\Api\TransferController::class, 'history']);
        
        // Basket operations
        Route::prefix('{uuid}/baskets')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\BasketAccountController::class, 'getBasketHoldings']);
            Route::post('/decompose', [\App\Http\Controllers\Api\BasketAccountController::class, 'decompose'])->middleware('auth.api_or_sanctum:write');
            Route::post('/compose', [\App\Http\Controllers\Api\BasketAccountController::class, 'compose'])->middleware('auth.api_or_sanctum:write');
        });
    });

    // Asset management
    Route::prefix('assets')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AssetController::class, 'index']);
        Route::get('/{code}', [\App\Http\Controllers\Api\AssetController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\AssetController::class, 'store'])->middleware('auth.api_or_sanctum:write');
        Route::put('/{code}', [\App\Http\Controllers\Api\AssetController::class, 'update'])->middleware('auth.api_or_sanctum:write');
        Route::delete('/{code}', [\App\Http\Controllers\Api\AssetController::class, 'destroy'])->middleware('auth.api_or_sanctum:delete');
    });

    // Exchange rates
    Route::prefix('exchange-rates')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ExchangeRateController::class, 'index']);
        Route::get('/{from}/{to}', [\App\Http\Controllers\Api\ExchangeRateController::class, 'show']);
        Route::get('/{from}/{to}/convert', [\App\Http\Controllers\Api\ExchangeRateController::class, 'convert']);
        Route::post('/refresh', [\App\Http\Controllers\Api\ExchangeRateController::class, 'refresh'])->middleware('auth.api_or_sanctum:write');
    });

    // Transfers
    Route::prefix('transfers')->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\TransferController::class, 'store'])->middleware(['transaction.rate_limit:transfer', 'auth.api_or_sanctum:write']);
        Route::get('/{uuid}', [\App\Http\Controllers\Api\TransferController::class, 'show']);
    });

    // Basket assets (protected operations)
    Route::prefix('baskets')->group(function () {
        Route::post('/', [\App\Http\Controllers\Api\BasketController::class, 'store'])->middleware('auth.api_or_sanctum:write');
        Route::post('/{code}/rebalance', [\App\Http\Controllers\Api\BasketController::class, 'rebalance'])->middleware('auth.api_or_sanctum:write');
    });

    // Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\TransactionController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\TransactionController::class, 'show']);
    });
    
    // Compliance and KYC/AML
    Route::prefix('compliance')->group(function () {
        Route::get('/kyc/status', [ComplianceController::class, 'getKycStatus']);
        Route::post('/kyc/start', [ComplianceController::class, 'startVerification']);
        Route::post('/kyc/{verificationId}/document', [ComplianceController::class, 'uploadDocument']);
        Route::post('/kyc/{verificationId}/selfie', [ComplianceController::class, 'uploadSelfie']);
        
        Route::get('/aml/status', [ComplianceController::class, 'getScreeningStatus']);
        Route::post('/aml/request-screening', [ComplianceController::class, 'requestScreening']);
        
        Route::get('/risk-profile', [ComplianceController::class, 'getRiskProfile']);
        Route::post('/check-transaction', [ComplianceController::class, 'checkTransactionEligibility']);
    });
    
    // Bank Integration endpoints
    Route::prefix('banks')->group(function () {
        Route::get('/available', [BankIntegrationController::class, 'getAvailableBanks']);
        Route::get('/health/{bankCode}', [BankIntegrationController::class, 'getBankHealth']);
        Route::get('/recommendations', [BankIntegrationController::class, 'getRecommendedBanks']);
        
        // User bank connections
        Route::get('/connections', [BankIntegrationController::class, 'getUserConnections']);
        Route::post('/connect', [BankIntegrationController::class, 'connectBank']);
        Route::delete('/disconnect/{bankCode}', [BankIntegrationController::class, 'disconnectBank']);
        
        // Bank accounts
        Route::get('/accounts', [BankIntegrationController::class, 'getBankAccounts']);
        Route::post('/accounts/sync/{bankCode}', [BankIntegrationController::class, 'syncAccounts']);
        
        // Balances and transfers
        Route::get('/balance/aggregate', [BankIntegrationController::class, 'getAggregatedBalance']);
        Route::post('/transfer', [BankIntegrationController::class, 'initiateTransfer'])->middleware('transaction.rate_limit:transfer');
    });
});