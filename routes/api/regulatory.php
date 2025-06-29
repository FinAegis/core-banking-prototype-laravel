<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EnhancedRegulatoryController;

Route::middleware(['auth:sanctum'])->prefix('regulatory')->group(function () {
    
    // Report Management
    Route::prefix('reports')->group(function () {
        Route::get('/', [EnhancedRegulatoryController::class, 'index'])
            ->name('regulatory.reports.index');
        Route::get('/{report}', [EnhancedRegulatoryController::class, 'show'])
            ->name('regulatory.reports.show');
        Route::get('/{report}/download', [EnhancedRegulatoryController::class, 'download'])
            ->name('regulatory.reports.download');
        
        // Report Generation (Admin only)
        Route::middleware('admin')->group(function () {
            Route::post('/generate/ctr', [EnhancedRegulatoryController::class, 'generateEnhancedCTR'])
                ->name('regulatory.generate.ctr');
            Route::post('/generate/sar', [EnhancedRegulatoryController::class, 'generateEnhancedSAR'])
                ->name('regulatory.generate.sar');
            Route::post('/generate/aml', [EnhancedRegulatoryController::class, 'generateAMLReport'])
                ->name('regulatory.generate.aml');
            Route::post('/generate/ofac', [EnhancedRegulatoryController::class, 'generateOFACReport'])
                ->name('regulatory.generate.ofac');
            Route::post('/generate/bsa', [EnhancedRegulatoryController::class, 'generateBSAReport'])
                ->name('regulatory.generate.bsa');
        });
    });
    
    // Filing Management (Admin only)
    Route::middleware('admin')->prefix('filings')->group(function () {
        Route::post('/reports/{report}/submit', [EnhancedRegulatoryController::class, 'submitReport'])
            ->name('regulatory.filings.submit');
        Route::get('/{filing}/status', [EnhancedRegulatoryController::class, 'checkFilingStatus'])
            ->name('regulatory.filings.status');
        Route::post('/{filing}/retry', [EnhancedRegulatoryController::class, 'retryFiling'])
            ->name('regulatory.filings.retry');
    });
    
    // Threshold Management (Admin only)
    Route::middleware('admin')->prefix('thresholds')->group(function () {
        Route::get('/', [EnhancedRegulatoryController::class, 'getThresholds'])
            ->name('regulatory.thresholds.index');
        Route::put('/{threshold}', [EnhancedRegulatoryController::class, 'updateThreshold'])
            ->name('regulatory.thresholds.update');
    });
    
    // Dashboard
    Route::get('/dashboard', [EnhancedRegulatoryController::class, 'dashboard'])
        ->name('regulatory.dashboard');
});