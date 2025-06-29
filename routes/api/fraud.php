<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Fraud\FraudDetectionController;
use App\Http\Controllers\API\Fraud\FraudCaseController;
use App\Http\Controllers\API\Fraud\FraudRuleController;

Route::middleware(['auth:sanctum'])->prefix('fraud')->group(function () {
    
    // Fraud Detection
    Route::prefix('detection')->group(function () {
        Route::post('analyze/transaction/{transaction}', [FraudDetectionController::class, 'analyzeTransaction'])
            ->name('fraud.analyze.transaction');
        Route::post('analyze/user/{user}', [FraudDetectionController::class, 'analyzeUser'])
            ->name('fraud.analyze.user');
        Route::get('score/{fraudScore}', [FraudDetectionController::class, 'getFraudScore'])
            ->name('fraud.score.show');
        Route::put('score/{fraudScore}/outcome', [FraudDetectionController::class, 'updateOutcome'])
            ->name('fraud.score.outcome');
        Route::get('statistics', [FraudDetectionController::class, 'getStatistics'])
            ->name('fraud.statistics');
        Route::get('model/metrics', [FraudDetectionController::class, 'getModelMetrics'])
            ->name('fraud.model.metrics');
    });
    
    // Fraud Cases
    Route::prefix('cases')->group(function () {
        Route::get('/', [FraudCaseController::class, 'index'])
            ->name('fraud.cases.index');
        Route::get('statistics', [FraudCaseController::class, 'statistics'])
            ->name('fraud.cases.statistics');
        Route::get('{case}', [FraudCaseController::class, 'show'])
            ->name('fraud.cases.show');
        Route::put('{case}', [FraudCaseController::class, 'update'])
            ->name('fraud.cases.update');
        Route::post('{case}/resolve', [FraudCaseController::class, 'resolve'])
            ->name('fraud.cases.resolve');
        Route::post('{case}/escalate', [FraudCaseController::class, 'escalate'])
            ->name('fraud.cases.escalate');
        Route::post('{case}/assign', [FraudCaseController::class, 'assign'])
            ->name('fraud.cases.assign');
        Route::post('{case}/evidence', [FraudCaseController::class, 'addEvidence'])
            ->name('fraud.cases.evidence');
        Route::get('{case}/timeline', [FraudCaseController::class, 'timeline'])
            ->name('fraud.cases.timeline');
    });
    
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