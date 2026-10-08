<?php

declare(strict_types=1);

use App\Http\Controllers\Api\RiskAnalysisController;
use App\Http\Controllers\Api\TransactionMonitoringController;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

// Risk Analysis endpoints
Route::middleware('auth:sanctum')->prefix('risk')->group(function () {
    // User risk endpoints
    Route::prefix('users/{userId}')->group(function () {
        Route::get('/profile', [RiskAnalysisController::class, 'getUserRiskProfile']);
        Route::get('/history', [RiskAnalysisController::class, 'getRiskHistory']);
        Route::get('/devices', [RiskAnalysisController::class, 'getDeviceHistory']);
    });

    // Transaction risk endpoints
    Route::get('/transactions/{transactionId}/analyze', [RiskAnalysisController::class, 'analyzeTransaction']);
    Route::post('/transactions/{transactionId}/analyze', [RiskAnalysisController::class, 'analyzeTransaction']);

    // General risk endpoints
    Route::post('/calculate', [RiskAnalysisController::class, 'calculateRiskScore']);
    Route::post('/device-fingerprint', [RiskAnalysisController::class, 'storeDeviceFingerprint']);
    Route::get('/factors', [RiskAnalysisController::class, 'getRiskFactors']);
    Route::get('/models', [RiskAnalysisController::class, 'getRiskModels']);
});

// Transaction Monitoring endpoints: compliance staff only (the list and
// details cover every user's transactions; flag, clear, rules and thresholds
// are compliance decisions)
Route::middleware(['auth:sanctum', RoleMiddleware::using(StaffRoles::COMPLIANCE)])->prefix('transaction-monitoring')->group(function () {
    // Transaction monitoring
    Route::get('/', [TransactionMonitoringController::class, 'getMonitoredTransactions']);
    Route::get('/transactions/{transaction}', [TransactionMonitoringController::class, 'getTransactionDetails']);
    Route::post('/transactions/{transaction}/flag', [TransactionMonitoringController::class, 'flagTransaction']);
    Route::post('/transactions/{transaction}/clear', [TransactionMonitoringController::class, 'clearTransaction']);

    // Analysis
    Route::post('/analyze/batch', [TransactionMonitoringController::class, 'analyzeBatch']);
    Route::post('/analyze/{transaction}', [TransactionMonitoringController::class, 'analyzeRealtime']);

    // Rules management
    Route::get('/rules', [TransactionMonitoringController::class, 'getRules']);
    Route::post('/rules', [TransactionMonitoringController::class, 'createRule']);
    Route::put('/rules/{rule}', [TransactionMonitoringController::class, 'updateRule']);
    Route::delete('/rules/{rule}', [TransactionMonitoringController::class, 'deleteRule']);

    // Patterns and thresholds
    Route::get('/patterns', [TransactionMonitoringController::class, 'getPatterns']);
    Route::get('/thresholds', [TransactionMonitoringController::class, 'getThresholds']);
    Route::put('/thresholds', [TransactionMonitoringController::class, 'updateThresholds']);
});
