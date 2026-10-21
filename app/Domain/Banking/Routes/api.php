<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BankAlertingController;
use App\Http\Controllers\Api\BankAllocationController;
use App\Http\Controllers\Api\Banking\AccountVerificationController;
use App\Http\Controllers\Api\Banking\BankingController;
use App\Http\Controllers\Api\Banking\BankWebhookController;
use App\Http\Controllers\Api\BatchProcessingController;
use App\Http\Controllers\Api\DailyReconciliationController;
use App\Http\Controllers\Api\RegulatoryReportingController;
use App\Http\Controllers\Api\WorkflowMonitoringController;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

// Bank webhook endpoints (no auth — signature-verified)
Route::prefix('webhooks/bank')->middleware('api.rate_limit:webhook')->group(function () {
    Route::post('/{provider}/transfer-update', [BankWebhookController::class, 'transferUpdate']);
    Route::post('/{provider}/account-update', [BankWebhookController::class, 'accountUpdate']);
});

// Banking operations endpoints
Route::middleware('auth:sanctum')->group(function () {
    // User-facing banking endpoints (v2)
    Route::prefix('v2/banks')->group(function () {
        Route::post('/connect', [BankingController::class, 'connect']);
        Route::delete('/disconnect/{connectionId}', [BankingController::class, 'disconnect']);
        Route::get('/connections', [BankingController::class, 'connections']);
        Route::get('/accounts', [BankingController::class, 'accounts']);
        Route::post('/accounts/sync/{connectionId}', [BankingController::class, 'syncAccounts']);
        Route::post('/transfer', [BankingController::class, 'initiateTransfer']);
        Route::get('/transfer/{id}/status', [BankingController::class, 'transferStatus']);
        Route::get('/health/{bankCode}', [BankingController::class, 'bankHealth']);

        // Account verification
        Route::prefix('verify')->group(function () {
            Route::post('/micro-deposit/initiate', [AccountVerificationController::class, 'initiateMicroDeposit']);
            Route::post('/micro-deposit/confirm', [AccountVerificationController::class, 'confirmMicroDeposit']);
            Route::post('/instant', [AccountVerificationController::class, 'instantVerify']);
        });
    });
    // Batch Processing endpoints: platform operators only (the history and
    // status cover every user's batches; execute and cancel also require the
    // admin role in the controller)
    Route::prefix('batch-operations')->middleware(RoleMiddleware::using(StaffRoles::OPERATORS))->group(function () {
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

    // Regulatory Reporting endpoints: compliance staff only (the CTR, SAR,
    // KYC and summary reports cover every user)
    Route::prefix('regulatory')->middleware(RoleMiddleware::using(StaffRoles::COMPLIANCE))->group(function () {
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

    // Daily Reconciliation endpoints: platform operators only
    Route::prefix('reconciliation')->middleware(RoleMiddleware::using(StaffRoles::OPERATORS))->group(function () {
        Route::post('/trigger', [DailyReconciliationController::class, 'triggerReconciliation']);
        Route::get('/latest', [DailyReconciliationController::class, 'getLatestReport']);
        Route::get('/history', [DailyReconciliationController::class, 'getHistory']);
        Route::get('/reports/{date}', [DailyReconciliationController::class, 'getReportByDate']);
        Route::get('/metrics', [DailyReconciliationController::class, 'getMetrics']);
        Route::get('/status', [DailyReconciliationController::class, 'getStatus']);
    });

    // Bank Health & Alerting endpoints: platform operators only
    Route::prefix('bank-health')->middleware(RoleMiddleware::using(StaffRoles::OPERATORS))->group(function () {
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

    // Workflow/Saga Monitoring endpoints: platform operators only (stored
    // workflow arguments and output cover every user's operations)
    Route::prefix('workflows')->middleware(['api.rate_limit:admin', RoleMiddleware::using(StaffRoles::OPERATORS)])->group(function () {
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
