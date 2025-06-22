<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AccountBalanceController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\CustodianController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\PollController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\Api\BasketController;
use App\Http\Controllers\Api\BasketAccountController;
use App\Http\Controllers\Api\StablecoinController;
use App\Http\Controllers\Api\StablecoinOperationsController;
use App\Http\Controllers\Api\UserVotingController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\GdprController;
use App\Http\Controllers\Api\BasketPerformanceController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication endpoints (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login']);
    
    // Protected auth endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout']);
        Route::post('/logout-all', [LoginController::class, 'logoutAll']);
        Route::post('/refresh', [LoginController::class, 'refresh']);
        Route::get('/user', [LoginController::class, 'user']);
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    // Account management endpoints
    Route::post('/accounts', [AccountController::class, 'store']);
    Route::get('/accounts/{uuid}', [AccountController::class, 'show']);
    Route::delete('/accounts/{uuid}', [AccountController::class, 'destroy']);
    Route::post('/accounts/{uuid}/freeze', [AccountController::class, 'freeze']);
    Route::post('/accounts/{uuid}/unfreeze', [AccountController::class, 'unfreeze']);

    // Transaction endpoints
    Route::post('/accounts/{uuid}/deposit', [TransactionController::class, 'deposit']);
    Route::post('/accounts/{uuid}/withdraw', [TransactionController::class, 'withdraw']);
    Route::get('/accounts/{uuid}/transactions', [TransactionController::class, 'history']);

    // Transfer endpoints
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::get('/transfers/{uuid}', [TransferController::class, 'show']);
    Route::get('/accounts/{uuid}/transfers', [TransferController::class, 'history']);

    // Balance inquiry endpoints (legacy)
    Route::get('/accounts/{uuid}/balance', [BalanceController::class, 'show']);
    Route::get('/accounts/{uuid}/balance/summary', [BalanceController::class, 'summary']);
    
    // Multi-asset balance endpoints
    Route::get('/accounts/{uuid}/balances', [AccountBalanceController::class, 'show']);
    Route::get('/balances', [AccountBalanceController::class, 'index']);
    
    // Custodian integration endpoints
    Route::prefix('custodians')->group(function () {
        Route::get('/', [CustodianController::class, 'index']);
        Route::get('/{custodian}/account-info', [CustodianController::class, 'accountInfo']);
        Route::get('/{custodian}/balance', [CustodianController::class, 'balance']);
        Route::post('/{custodian}/transfer', [CustodianController::class, 'transfer']);
        Route::get('/{custodian}/transactions', [CustodianController::class, 'transactionHistory']);
        Route::get('/{custodian}/transactions/{transactionId}', [CustodianController::class, 'transactionStatus']);
    });

    // Governance endpoints
    Route::prefix('polls')->group(function () {
        Route::get('/', [PollController::class, 'index']);
        Route::get('/active', [PollController::class, 'active']);
        Route::post('/', [PollController::class, 'store']);
        Route::get('/{uuid}', [PollController::class, 'show']);
        Route::post('/{uuid}/activate', [PollController::class, 'activate']);
        Route::post('/{uuid}/vote', [PollController::class, 'vote']);
        Route::get('/{uuid}/results', [PollController::class, 'results']);
        Route::get('/{uuid}/voting-power', [PollController::class, 'votingPower']);
    });

    Route::prefix('votes')->group(function () {
        Route::get('/', [VoteController::class, 'index']);
        Route::get('/stats', [VoteController::class, 'stats']);
        Route::get('/{id}', [VoteController::class, 'show']);
        Route::post('/{id}/verify', [VoteController::class, 'verify']);
    });
    
    // User-friendly voting interface
    Route::prefix('voting')->group(function () {
        Route::get('/polls', [UserVotingController::class, 'getActivePolls']);
        Route::get('/polls/upcoming', [UserVotingController::class, 'getUpcomingPolls']);
        Route::get('/polls/history', [UserVotingController::class, 'getVotingHistory']);
        Route::post('/polls/{uuid}/vote', [UserVotingController::class, 'submitBasketVote']);
        Route::get('/dashboard', [UserVotingController::class, 'getDashboard']);
    });
});

// Public asset and exchange rate endpoints (no auth required for read-only access)
Route::prefix('v1')->group(function () {
    // Asset management endpoints
    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/{code}', [AssetController::class, 'show']);
    
    // Exchange rate endpoints
    Route::get('/exchange-rates', [ExchangeRateController::class, 'index']);
    Route::get('/exchange-rates/{from}/{to}', [ExchangeRateController::class, 'show']);
    Route::get('/exchange-rates/{from}/{to}/convert', [ExchangeRateController::class, 'convert']);
    
    // Exchange rate provider endpoints
    Route::prefix('exchange-providers')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ExchangeRateProviderController::class, 'index']);
        Route::get('/{provider}/rate', [\App\Http\Controllers\Api\ExchangeRateProviderController::class, 'getRate']);
        Route::get('/compare', [\App\Http\Controllers\Api\ExchangeRateProviderController::class, 'compareRates']);
        Route::get('/aggregated', [\App\Http\Controllers\Api\ExchangeRateProviderController::class, 'getAggregatedRate']);
        Route::post('/refresh', [\App\Http\Controllers\Api\ExchangeRateProviderController::class, 'refresh'])->middleware('auth:sanctum');
        Route::get('/historical', [\App\Http\Controllers\Api\ExchangeRateProviderController::class, 'historical']);
        Route::post('/validate', [\App\Http\Controllers\Api\ExchangeRateProviderController::class, 'validate']);
    });
});

// Basket endpoints
Route::prefix('v2')->group(function () {
    // Public basket endpoints
    Route::prefix('baskets')->group(function () {
        Route::get('/', [BasketController::class, 'index']);
        Route::get('/{code}', [BasketController::class, 'show']);
        Route::get('/{code}/value', [BasketController::class, 'getValue']);
        Route::get('/{code}/history', [BasketController::class, 'getHistory']);
        
        // Performance tracking endpoints
        Route::get('/{code}/performance', [BasketPerformanceController::class, 'show']);
        Route::get('/{code}/performance/history', [BasketPerformanceController::class, 'history']);
        Route::get('/{code}/performance/summary', [BasketPerformanceController::class, 'summary']);
        Route::get('/{code}/performance/components', [BasketPerformanceController::class, 'components']);
        Route::get('/{code}/performance/top-performers', [BasketPerformanceController::class, 'topPerformers']);
        Route::get('/{code}/performance/worst-performers', [BasketPerformanceController::class, 'worstPerformers']);
        Route::get('/{code}/performance/compare', [BasketPerformanceController::class, 'compare']);
    });
    
    // Protected basket endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/baskets', [BasketController::class, 'store']);
        Route::post('/baskets/{code}/rebalance', [BasketController::class, 'rebalance']);
        Route::post('/baskets/{code}/performance/calculate', [BasketPerformanceController::class, 'calculate']);
        
        // Basket operations on accounts
        Route::prefix('accounts/{uuid}/baskets')->group(function () {
            Route::get('/', [BasketAccountController::class, 'getBasketHoldings']);
            Route::post('/decompose', [BasketAccountController::class, 'decompose']);
            Route::post('/compose', [BasketAccountController::class, 'compose']);
        });
    });
    
    // Stablecoin management endpoints
    Route::prefix('stablecoins')->group(function () {
        Route::get('/', [StablecoinController::class, 'index']);
        Route::get('/metrics', [StablecoinController::class, 'systemMetrics']);
        Route::get('/health', [StablecoinController::class, 'systemHealth']);
        Route::get('/{code}', [StablecoinController::class, 'show']);
        Route::get('/{code}/metrics', [StablecoinController::class, 'metrics']);
        Route::get('/{code}/collateral-distribution', [StablecoinController::class, 'collateralDistribution']);
        Route::post('/{code}/execute-stability', [StablecoinController::class, 'executeStabilityMechanism']);
        
        // Admin operations (require additional permissions in real implementation)
        Route::post('/', [StablecoinController::class, 'store']);
        Route::put('/{code}', [StablecoinController::class, 'update']);
        Route::post('/{code}/deactivate', [StablecoinController::class, 'deactivate']);
        Route::post('/{code}/reactivate', [StablecoinController::class, 'reactivate']);
    });
    
    // Stablecoin operations endpoints
    Route::prefix('stablecoin-operations')->group(function () {
        Route::post('/mint', [StablecoinOperationsController::class, 'mint']);
        Route::post('/burn', [StablecoinOperationsController::class, 'burn']);
        Route::post('/add-collateral', [StablecoinOperationsController::class, 'addCollateral']);
        
        // Position management
        Route::get('/accounts/{accountUuid}/positions', [StablecoinOperationsController::class, 'getAccountPositions']);
        Route::get('/positions/at-risk', [StablecoinOperationsController::class, 'getPositionsAtRisk']);
        Route::get('/positions/{positionUuid}', [StablecoinOperationsController::class, 'getPositionDetails']);
        
        // Liquidation operations
        Route::get('/liquidation/opportunities', [StablecoinOperationsController::class, 'getLiquidationOpportunities']);
        Route::post('/liquidation/execute', [StablecoinOperationsController::class, 'executeAutoLiquidation']);
        Route::post('/liquidation/positions/{positionUuid}', [StablecoinOperationsController::class, 'liquidatePosition']);
        Route::get('/liquidation/positions/{positionUuid}/reward', [StablecoinOperationsController::class, 'calculateLiquidationReward']);
        
        // Simulation and analytics
        Route::post('/simulation/{stablecoinCode}/mass-liquidation', [StablecoinOperationsController::class, 'simulateMassLiquidation']);
    });
});

// Compliance and KYC endpoints
Route::middleware('auth:sanctum')->prefix('compliance')->group(function () {
    // KYC endpoints
    Route::prefix('kyc')->group(function () {
        Route::get('/status', [KycController::class, 'status']);
        Route::get('/requirements', [KycController::class, 'requirements']);
        Route::post('/submit', [KycController::class, 'submit']);
        Route::get('/documents/{documentId}/download', [KycController::class, 'downloadDocument']);
    });
    
    // GDPR endpoints
    Route::prefix('gdpr')->group(function () {
        Route::get('/consent', [GdprController::class, 'consentStatus']);
        Route::post('/consent', [GdprController::class, 'updateConsent']);
        Route::post('/export', [GdprController::class, 'requestDataExport']);
        Route::post('/delete', [GdprController::class, 'requestDeletion']);
        Route::get('/retention-policy', [GdprController::class, 'retentionPolicy']);
    });
});

// Custodian webhook endpoints (no auth required - signature verification is used instead)
Route::prefix('webhooks/custodian')->group(function () {
    Route::post('/paysera', [\App\Http\Controllers\Api\CustodianWebhookController::class, 'paysera']);
    Route::post('/santander', [\App\Http\Controllers\Api\CustodianWebhookController::class, 'santander']);
    Route::post('/mock', [\App\Http\Controllers\Api\CustodianWebhookController::class, 'mock']);
});

// Include BIAN-compliant routes
require __DIR__.'/api-bian.php';

// Include V2 public API routes
Route::prefix('v2')->group(function () {
    require __DIR__.'/api-v2.php';
});
