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
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Auth\TwoFactorAuthController;
use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\TransactionReversalController;
use App\Http\Controllers\Api\BatchProcessingController;
use App\Http\Controllers\Api\BankAllocationController;
use App\Http\Controllers\Api\RegulatoryReportingController;
use App\Http\Controllers\Api\DailyReconciliationController;
use App\Http\Controllers\Api\BankAlertingController;
use App\Http\Controllers\Api\WorkflowMonitoringController;
use App\Http\Controllers\Api\SubProductController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\StatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication endpoints (public)
Route::prefix('auth')->middleware('api.rate_limit:auth')->group(function () {
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login']);
    
    // Password reset endpoints (public)
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
    
    // Email verification endpoints
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('api.verification.verify');
    
    // Social authentication endpoints
    Route::get('/social/{provider}', [SocialAuthController::class, 'redirect']);
    Route::post('/social/{provider}/callback', [SocialAuthController::class, 'callback']);
    
    // Protected auth endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout']);
        Route::post('/logout-all', [LoginController::class, 'logoutAll']);
        Route::post('/refresh', [LoginController::class, 'refresh']);
        Route::get('/user', [LoginController::class, 'user']);
        
        // Email verification resend
        Route::post('/resend-verification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1');
        
        // Two-factor authentication endpoints
        Route::prefix('2fa')->group(function () {
            Route::post('/enable', [TwoFactorAuthController::class, 'enable']);
            Route::post('/confirm', [TwoFactorAuthController::class, 'confirm']);
            Route::post('/disable', [TwoFactorAuthController::class, 'disable']);
            Route::post('/verify', [TwoFactorAuthController::class, 'verify']);
            Route::post('/recovery-codes', [TwoFactorAuthController::class, 'regenerateRecoveryCodes']);
        });
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    // Sub-product status for authenticated users
    Route::get('/sub-products/enabled', [SubProductController::class, 'enabled']);
    
    // Account management endpoints (query rate limiting)
    Route::middleware('api.rate_limit:query')->group(function () {
        Route::post('/accounts', [AccountController::class, 'store']);
        Route::get('/accounts/{uuid}', [AccountController::class, 'show']);
        Route::delete('/accounts/{uuid}', [AccountController::class, 'destroy']);
        Route::post('/accounts/{uuid}/freeze', [AccountController::class, 'freeze']);
        Route::post('/accounts/{uuid}/unfreeze', [AccountController::class, 'unfreeze']);
        Route::get('/accounts/{uuid}/transactions', [TransactionController::class, 'history']);
    });

    // Transaction endpoints (transaction rate limiting)
    Route::post('/accounts/{uuid}/deposit', [TransactionController::class, 'deposit'])->middleware('transaction.rate_limit:deposit');
    Route::post('/accounts/{uuid}/withdraw', [TransactionController::class, 'withdraw'])->middleware('transaction.rate_limit:withdraw');

    // Transfer endpoints (transaction rate limiting)
    Route::post('/transfers', [TransferController::class, 'store'])->middleware('transaction.rate_limit:transfer');
    Route::middleware('api.rate_limit:query')->group(function () {
        Route::get('/transfers/{uuid}', [TransferController::class, 'show']);
        Route::get('/accounts/{uuid}/transfers', [TransferController::class, 'history']);

        // Balance inquiry endpoints (legacy)
        Route::get('/accounts/{uuid}/balance', [BalanceController::class, 'show']);
        Route::get('/accounts/{uuid}/balance/summary', [BalanceController::class, 'summary']);
        
        // Multi-asset balance endpoints
        Route::get('/accounts/{uuid}/balances', [AccountBalanceController::class, 'show']);
        Route::get('/balances', [AccountBalanceController::class, 'index']);
    });
    
    // Currency conversion endpoint (transaction rate limiting, requires exchange sub-product)
    Route::post('/exchange/convert', [ExchangeRateController::class, 'convertCurrency'])
        ->middleware(['transaction.rate_limit:convert', 'sub_product:exchange']);
    
    // Custodian integration endpoints
    Route::prefix('custodians')->group(function () {
        Route::get('/', [CustodianController::class, 'index']);
        Route::get('/{custodian}/account-info', [CustodianController::class, 'accountInfo']);
        Route::get('/{custodian}/balance', [CustodianController::class, 'balance']);
        Route::post('/{custodian}/transfer', [CustodianController::class, 'transfer']);
        Route::get('/{custodian}/transactions', [CustodianController::class, 'transactionHistory']);
        Route::get('/{custodian}/transactions/{transactionId}', [CustodianController::class, 'transactionStatus']);
    });

    // Governance endpoints (query rate limiting for reads, vote rate limiting for votes)
    Route::prefix('polls')->group(function () {
        Route::middleware('api.rate_limit:query')->group(function () {
            Route::get('/', [PollController::class, 'index']);
            Route::get('/active', [PollController::class, 'active']);
            Route::get('/{uuid}', [PollController::class, 'show']);
            Route::get('/{uuid}/results', [PollController::class, 'results']);
            Route::get('/{uuid}/voting-power', [PollController::class, 'votingPower']);
        });
        
        Route::middleware('api.rate_limit:admin')->group(function () {
            Route::post('/', [PollController::class, 'store']);
            Route::post('/{uuid}/activate', [PollController::class, 'activate']);
        });
        
        Route::post('/{uuid}/vote', [PollController::class, 'vote'])->middleware('transaction.rate_limit:vote');
    });

    Route::prefix('votes')->middleware('api.rate_limit:query')->group(function () {
        Route::get('/', [VoteController::class, 'index']);
        Route::get('/stats', [VoteController::class, 'stats']);
        Route::get('/{id}', [VoteController::class, 'show']);
        Route::post('/{id}/verify', [VoteController::class, 'verify']);
    });
    
    // User-friendly voting interface (query rate limiting for reads, vote rate limiting for votes)
    Route::prefix('voting')->group(function () {
        Route::middleware('api.rate_limit:query')->group(function () {
            Route::get('/polls', [UserVotingController::class, 'getActivePolls']);
            Route::get('/polls/upcoming', [UserVotingController::class, 'getUpcomingPolls']);
            Route::get('/polls/history', [UserVotingController::class, 'getVotingHistory']);
            Route::get('/dashboard', [UserVotingController::class, 'getDashboard']);
        });
        
        Route::post('/polls/{uuid}/vote', [UserVotingController::class, 'submitBasketVote'])->middleware('transaction.rate_limit:vote');
    });
    
    // Transaction Reversal endpoints
    Route::post('/accounts/{uuid}/transactions/reverse', [TransactionReversalController::class, 'reverseTransaction']);
    Route::get('/accounts/{uuid}/transactions/reversals', [TransactionReversalController::class, 'getReversalHistory']);
    Route::get('/transactions/reversals/{reversalId}/status', [TransactionReversalController::class, 'getReversalStatus']);
    
    // Batch Processing endpoints
    Route::prefix('batch-operations')->group(function () {
        Route::post('/execute', [BatchProcessingController::class, 'executeBatch']);
        Route::get('/{batchId}/status', [BatchProcessingController::class, 'getBatchStatus']);
        Route::get('/', [BatchProcessingController::class, 'getBatchHistory']);
        Route::post('/{batchId}/cancel', [BatchProcessingController::class, 'cancelBatch']);
    });
    
    // Bank Allocation endpoints
    Route::prefix('bank-allocations')->group(function () {
        Route::get('/', [BankAllocationController::class, 'index']);
        Route::put('/', [BankAllocationController::class, 'update']);
        Route::post('/banks', [BankAllocationController::class, 'addBank']);
        Route::delete('/banks/{bankCode}', [BankAllocationController::class, 'removeBank']);
        Route::put('/primary/{bankCode}', [BankAllocationController::class, 'setPrimaryBank']);
        Route::get('/available-banks', [BankAllocationController::class, 'getAvailableBanks']);
        Route::post('/distribution-preview', [BankAllocationController::class, 'previewDistribution']);
    });
    
    // Regulatory Reporting endpoints (admin only)
    Route::prefix('regulatory')->group(function () {
        Route::post('/reports/ctr', [RegulatoryReportingController::class, 'generateCTR']);
        Route::post('/reports/sar-candidates', [RegulatoryReportingController::class, 'generateSARCandidates']);
        Route::post('/reports/compliance-summary', [RegulatoryReportingController::class, 'generateComplianceSummary']);
        Route::post('/reports/kyc', [RegulatoryReportingController::class, 'generateKycReport']);
        Route::get('/reports', [RegulatoryReportingController::class, 'listReports']);
        Route::get('/reports/{filename}', [RegulatoryReportingController::class, 'getReport']);
        Route::get('/reports/{filename}/download', [RegulatoryReportingController::class, 'downloadReport'])->name('api.regulatory.download');
        Route::delete('/reports/{filename}', [RegulatoryReportingController::class, 'deleteReport']);
        Route::get('/metrics', [RegulatoryReportingController::class, 'getMetrics']);
    });
    
    // Daily Reconciliation endpoints (admin only)
    Route::prefix('reconciliation')->group(function () {
        Route::post('/trigger', [DailyReconciliationController::class, 'triggerReconciliation']);
        Route::get('/latest', [DailyReconciliationController::class, 'getLatestReport']);
        Route::get('/history', [DailyReconciliationController::class, 'getHistory']);
        Route::get('/reports/{date}', [DailyReconciliationController::class, 'getReportByDate']);
        Route::get('/metrics', [DailyReconciliationController::class, 'getMetrics']);
        Route::get('/status', [DailyReconciliationController::class, 'getStatus']);
    });
    
    // Bank Health & Alerting endpoints (admin only)
    Route::prefix('bank-health')->group(function () {
        Route::post('/check', [BankAlertingController::class, 'triggerHealthCheck']);
        Route::get('/status', [BankAlertingController::class, 'getHealthStatus']);
        Route::get('/custodians/{custodian}', [BankAlertingController::class, 'getCustodianHealth']);
        Route::get('/alerts/{custodian}/history', [BankAlertingController::class, 'getAlertHistory']);
        Route::get('/alerts/stats', [BankAlertingController::class, 'getAlertingStats']);
        Route::put('/alerts/config', [BankAlertingController::class, 'configureAlerts']);
        Route::get('/alerts/config', [BankAlertingController::class, 'getAlertConfiguration']);
        Route::post('/alerts/test', [BankAlertingController::class, 'testAlert']);
        Route::post('/alerts/{alertId}/acknowledge', [BankAlertingController::class, 'acknowledgeAlert']);
    });
    
    // Workflow/Saga Monitoring endpoints (admin only - admin rate limiting)
    Route::prefix('workflows')->middleware('api.rate_limit:admin')->group(function () {
        Route::get('/', [WorkflowMonitoringController::class, 'index']);
        Route::get('/stats', [WorkflowMonitoringController::class, 'stats']);
        Route::get('/metrics', [WorkflowMonitoringController::class, 'metrics']);
        Route::get('/search', [WorkflowMonitoringController::class, 'search']);
        Route::get('/status/{status}', [WorkflowMonitoringController::class, 'byStatus']);
        Route::get('/failed', [WorkflowMonitoringController::class, 'failed']);
        Route::get('/compensations', [WorkflowMonitoringController::class, 'compensations']);
        Route::get('/{id}', [WorkflowMonitoringController::class, 'show']);
    });
});

// Public asset and exchange rate endpoints (no auth required for read-only access - public rate limiting)
Route::middleware('api.rate_limit:public')->group(function () {
    Route::get('/exchange-rates', [ExchangeRateController::class, 'index']);
    Route::get('/exchange-rates/{from}/{to}', [ExchangeRateController::class, 'show']);
    
    // Sub-product status endpoints
    Route::prefix('sub-products')->group(function () {
        Route::get('/', [SubProductController::class, 'index']);
        Route::get('/{subProduct}', [SubProductController::class, 'show']);
    });
    
    // Public settings endpoints
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::get('/group/{group}', [SettingsController::class, 'group']);
    });
    
    // Public status endpoint
    Route::get('/status', [StatusController::class, 'api'])->name('status.api');
    
    // Exchange endpoints
    Route::prefix('exchange')->name('api.exchange.')->group(function () {
        // Public routes
        Route::get('/orderbook/{baseCurrency}/{quoteCurrency}', [App\Http\Controllers\Api\ExchangeController::class, 'getOrderBook']);
        Route::get('/markets', [App\Http\Controllers\Api\ExchangeController::class, 'getMarkets']);
        
        // Authenticated routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/orders', [App\Http\Controllers\Api\ExchangeController::class, 'placeOrder'])
                ->middleware('transaction.rate_limit:exchange_order');
            Route::delete('/orders/{orderId}', [App\Http\Controllers\Api\ExchangeController::class, 'cancelOrder']);
            Route::get('/orders', [App\Http\Controllers\Api\ExchangeController::class, 'getOrders']);
            Route::get('/trades', [App\Http\Controllers\Api\ExchangeController::class, 'getTrades']);
        });
    });
    
    // External Exchange endpoints
    Route::prefix('external-exchange')->name('api.external-exchange.')->group(function () {
        // Public routes
        Route::get('/connectors', [App\Http\Controllers\Api\ExternalExchangeController::class, 'connectors']);
        Route::get('/ticker/{base}/{quote}', [App\Http\Controllers\Api\ExternalExchangeController::class, 'ticker']);
        Route::get('/orderbook/{base}/{quote}', [App\Http\Controllers\Api\ExternalExchangeController::class, 'orderBook']);
        
        // Authenticated routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/arbitrage/{base}/{quote}', [App\Http\Controllers\Api\ExternalExchangeController::class, 'arbitrage']);
        });
    });
    
    // Liquidity Pool endpoints
    Route::prefix('liquidity')->name('api.liquidity.')->group(function () {
        // Public routes
        Route::get('/pools', [App\Http\Controllers\Api\LiquidityPoolController::class, 'index']);
        Route::get('/pools/{poolId}', [App\Http\Controllers\Api\LiquidityPoolController::class, 'show']);
        
        // Authenticated routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/pools', [App\Http\Controllers\Api\LiquidityPoolController::class, 'create']);
            Route::post('/add', [App\Http\Controllers\Api\LiquidityPoolController::class, 'addLiquidity']);
            Route::post('/remove', [App\Http\Controllers\Api\LiquidityPoolController::class, 'removeLiquidity']);
            Route::post('/swap', [App\Http\Controllers\Api\LiquidityPoolController::class, 'swap']);
            Route::get('/positions', [App\Http\Controllers\Api\LiquidityPoolController::class, 'positions']);
            Route::post('/claim-rewards', [App\Http\Controllers\Api\LiquidityPoolController::class, 'claimRewards']);
        });
    });
});

Route::prefix('v1')->middleware('api.rate_limit:public')->group(function () {
    // Asset management endpoints
    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/{code}', [AssetController::class, 'show']);
    
    // Exchange rate endpoints (legacy v1 support)
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
        Route::post('/validate', [\App\Http\Controllers\Api\ExchangeRateProviderController::class, 'validateRate']);
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
    
    // Stablecoin management endpoints (requires stablecoins sub-product to be enabled)
    Route::prefix('stablecoins')->middleware('sub_product:stablecoins')->group(function () {
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
    
    // Stablecoin operations endpoints (requires stablecoins sub-product to be enabled)
    Route::prefix('stablecoin-operations')->middleware('sub_product:stablecoins')->group(function () {
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

// Custodian webhook endpoints (no auth required - signature verification is used instead - webhook rate limiting)
Route::prefix('webhooks/custodian')->middleware('api.rate_limit:webhook')->group(function () {
    Route::post('/paysera', [\App\Http\Controllers\Api\CustodianWebhookController::class, 'paysera']);
    Route::post('/santander', [\App\Http\Controllers\Api\CustodianWebhookController::class, 'santander']);
    Route::post('/mock', [\App\Http\Controllers\Api\CustodianWebhookController::class, 'mock']);
});

// Payment processor webhook endpoints
Route::prefix('webhooks')->middleware('api.rate_limit:webhook')->group(function () {
    Route::post('/coinbase-commerce', [\App\Http\Controllers\CoinbaseWebhookController::class, 'handleWebhook']);
});

// Include BIAN-compliant routes
require __DIR__.'/api-bian.php';

// Include V2 public API routes
Route::prefix('v2')->middleware('ensure.json')->group(function () {
    require __DIR__.'/api-v2.php';
});

// Include fraud detection routes
require __DIR__.'/api/fraud.php';

// Include enhanced regulatory routes
require __DIR__.'/api/regulatory.php';
