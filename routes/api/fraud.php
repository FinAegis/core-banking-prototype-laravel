<?php

use App\Http\Controllers\Api\FraudDetectionController;
use App\Http\Controllers\Api\RiskAnalysisController;
use App\Http\Controllers\Api\TransactionMonitoringController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fraud Detection API Routes
|--------------------------------------------------------------------------
|
| These routes handle fraud detection, transaction monitoring, and risk analysis
|
*/

Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
    // Fraud Detection Dashboard
    Route::prefix('fraud')->group(function () {
        Route::get('/dashboard', [FraudDetectionController::class, 'dashboard']);
        Route::get('/alerts', [FraudDetectionController::class, 'getAlerts']);
        Route::get('/alerts/{id}', [FraudDetectionController::class, 'getAlertDetails']);
        Route::post('/alerts/{id}/acknowledge', [FraudDetectionController::class, 'acknowledgeAlert']);
        Route::post('/alerts/{id}/investigate', [FraudDetectionController::class, 'investigateAlert']);
        Route::get('/statistics', [FraudDetectionController::class, 'getStatistics']);
        Route::get('/patterns', [FraudDetectionController::class, 'getPatterns']);
        Route::get('/cases', [FraudDetectionController::class, 'getCases']);
        Route::get('/cases/{id}', [FraudDetectionController::class, 'getCaseDetails']);
        Route::post('/cases/{id}/update', [FraudDetectionController::class, 'updateCase']);
    });

    // Transaction Monitoring
    Route::prefix('monitoring')->group(function () {
        Route::get('/transactions', [TransactionMonitoringController::class, 'getMonitoredTransactions']);
        Route::get('/transactions/{id}', [TransactionMonitoringController::class, 'getTransactionDetails']);
        Route::post('/transactions/{id}/flag', [TransactionMonitoringController::class, 'flagTransaction']);
        Route::post('/transactions/{id}/clear', [TransactionMonitoringController::class, 'clearTransaction']);
        Route::get('/rules', [TransactionMonitoringController::class, 'getRules']);
        Route::post('/rules', [TransactionMonitoringController::class, 'createRule']);
        Route::put('/rules/{id}', [TransactionMonitoringController::class, 'updateRule']);
        Route::delete('/rules/{id}', [TransactionMonitoringController::class, 'deleteRule']);
        Route::get('/patterns', [TransactionMonitoringController::class, 'getPatterns']);
        Route::get('/thresholds', [TransactionMonitoringController::class, 'getThresholds']);
        Route::put('/thresholds', [TransactionMonitoringController::class, 'updateThresholds']);
    });

    // Risk Analysis
    Route::prefix('risk')->group(function () {
        Route::get('/profile/{userId}', [RiskAnalysisController::class, 'getUserRiskProfile']);
        Route::get('/transaction/{transactionId}', [RiskAnalysisController::class, 'analyzeTransaction']);
        Route::post('/calculate', [RiskAnalysisController::class, 'calculateRiskScore']);
        Route::get('/factors', [RiskAnalysisController::class, 'getRiskFactors']);
        Route::get('/models', [RiskAnalysisController::class, 'getRiskModels']);
        Route::get('/history/{userId}', [RiskAnalysisController::class, 'getRiskHistory']);
        Route::post('/device/fingerprint', [RiskAnalysisController::class, 'storeDeviceFingerprint']);
        Route::get('/device/{userId}', [RiskAnalysisController::class, 'getDeviceHistory']);
    });

    // Real-time Transaction Analysis
    Route::prefix('realtime')->group(function () {
        Route::post('/analyze', [TransactionMonitoringController::class, 'analyzeRealtime']);
        Route::post('/batch', [TransactionMonitoringController::class, 'analyzeBatch']);
        Route::get('/status/{analysisId}', [TransactionMonitoringController::class, 'getAnalysisStatus']);
    });
});