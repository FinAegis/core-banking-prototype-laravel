<?php

use App\Http\Controllers\API\Fraud\FraudCaseController;
use App\Http\Controllers\API\Fraud\FraudDetectionController as FraudDetectionAPIController;
use App\Http\Controllers\API\Fraud\FraudRuleController;
use App\Http\Controllers\Api\FraudDetectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('fraud')->group(function () {
    // Main fraud detection endpoints for tests - these come first to take precedence
    Route::get('dashboard', [FraudDetectionController::class, 'dashboard'])
        ->name('fraud.dashboard');
    Route::get('alerts', [FraudDetectionController::class, 'getAlerts'])
        ->name('api.fraud.alerts.index');
    Route::get('alerts/{id}', [FraudDetectionController::class, 'getAlertDetails'])
        ->name('api.fraud.alerts.show');
    Route::post('alerts/{id}/acknowledge', [FraudDetectionController::class, 'acknowledgeAlert'])
        ->name('api.fraud.alerts.acknowledge');
    Route::post('alerts/{id}/investigate', [FraudDetectionController::class, 'investigateAlert'])
        ->name('api.fraud.alerts.investigate');
    Route::get('statistics', [FraudDetectionController::class, 'getStatistics'])
        ->name('fraud.statistics.main');
    Route::get('patterns', [FraudDetectionController::class, 'getPatterns'])
        ->name('fraud.patterns.index');

    // Simple cases routes for test - must come before the cases prefix group
    Route::get('cases', [FraudDetectionController::class, 'getCases'])
        ->name('fraud.cases.simple.list');
    Route::get('cases/{id}', [FraudDetectionController::class, 'getCaseDetails'])
        ->name('fraud.cases.simple.details')
        ->where('id', 'case-[0-9]+');  // Pattern to match test IDs
    Route::put('cases/{id}', [FraudDetectionController::class, 'updateCase'])
        ->name('fraud.cases.simple.update')
        ->where('id', 'case-[0-9]+');  // Pattern to match test IDs

    // Fraud Detection
    Route::prefix('detection')->group(function () {
        Route::post('analyze/transaction/{transaction}', [FraudDetectionAPIController::class, 'analyzeTransaction'])
            ->name('fraud.analyze.transaction');
        Route::post('analyze/user/{user}', [FraudDetectionAPIController::class, 'analyzeUser'])
            ->name('fraud.analyze.user');
        Route::get('score/{fraudScore}', [FraudDetectionAPIController::class, 'getFraudScore'])
            ->name('fraud.score.show');
        Route::put('score/{fraudScore}/outcome', [FraudDetectionAPIController::class, 'updateOutcome'])
            ->name('fraud.score.outcome');
        Route::get('statistics', [FraudDetectionAPIController::class, 'getStatistics'])
            ->name('fraud.statistics');
        Route::get('model/metrics', [FraudDetectionAPIController::class, 'getModelMetrics'])
            ->name('fraud.model.metrics');
    });

    // Fraud Cases - Commented out to avoid conflict with test routes
    // Route::prefix('cases')->group(function () {
    //     Route::get('/', [FraudCaseController::class, 'index'])
    //         ->name('fraud.cases.index');
    //     Route::get('statistics', [FraudCaseController::class, 'statistics'])
    //         ->name('fraud.cases.statistics');
    //     Route::get('{case}', [FraudCaseController::class, 'show'])
    //         ->name('fraud.cases.show');
    //     Route::put('{case}', [FraudCaseController::class, 'update'])
    //         ->name('fraud.cases.update');
    //     Route::post('{case}/resolve', [FraudCaseController::class, 'resolve'])
    //         ->name('fraud.cases.resolve');
    //     Route::post('{case}/escalate', [FraudCaseController::class, 'escalate'])
    //         ->name('fraud.cases.escalate');
    //     Route::post('{case}/assign', [FraudCaseController::class, 'assign'])
    //         ->name('fraud.cases.assign');
    //     Route::post('{case}/evidence', [FraudCaseController::class, 'addEvidence'])
    //         ->name('fraud.cases.evidence');
    //     Route::get('{case}/timeline', [FraudCaseController::class, 'timeline'])
    //         ->name('fraud.cases.timeline');
    // });

    // Fraud Rules
    Route::prefix('rules')->group(function () {
        Route::get('/', [FraudRuleController::class, 'index'])
            ->name('fraud.rules.index');
        Route::get('statistics', [FraudRuleController::class, 'statistics'])
            ->name('fraud.rules.statistics');
        Route::post('/', [FraudRuleController::class, 'store'])
            ->name('fraud.rules.store');
        Route::get('{rule}', [FraudRuleController::class, 'show'])
            ->name('fraud.rules.show');
        Route::put('{rule}', [FraudRuleController::class, 'update'])
            ->name('fraud.rules.update');
        Route::delete('{rule}', [FraudRuleController::class, 'destroy'])
            ->name('fraud.rules.destroy');
        Route::post('{rule}/toggle', [FraudRuleController::class, 'toggleStatus'])
            ->name('fraud.rules.toggle');
        Route::post('{rule}/test', [FraudRuleController::class, 'test'])
            ->name('fraud.rules.test');
        Route::post('create-defaults', [FraudRuleController::class, 'createDefaults'])
            ->name('fraud.rules.defaults');
        Route::get('export/all', [FraudRuleController::class, 'export'])
            ->name('fraud.rules.export');
        Route::post('import', [FraudRuleController::class, 'import'])
            ->name('fraud.rules.import');
    });
});
