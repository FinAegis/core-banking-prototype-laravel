<?php

declare(strict_types=1);

use App\Http\Controllers\Api\RegTech\RegTechController;
use Illuminate\Support\Facades\Route;

// RegTech endpoints (regulatory compliance, MiFID II, MiCA, Travel Rule)
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('regtech')->name('api.regtech.')->group(function () {
    Route::get('/compliance/summary', [RegTechController::class, 'complianceSummary'])->name('compliance.summary');
    Route::get('/adapters', [RegTechController::class, 'adapters'])->name('adapters');
    Route::get('/regulations/applicable', [RegTechController::class, 'applicableRegulations'])->name('regulations.applicable');

    // Report submission & status (stricter rate limit on write operations)
    Route::post('/reports', [RegTechController::class, 'submitReport'])->middleware('throttle:10,1')->name('reports.submit');
    Route::get('/reports/{reference}/status', [RegTechController::class, 'reportStatus'])->name('reports.status');

    // MiFID II
    Route::get('/mifid/status', [RegTechController::class, 'mifidStatus'])->name('mifid.status');

    // MiCA
    Route::get('/mica/status', [RegTechController::class, 'micaStatus'])->name('mica.status');
    Route::post('/mica/whitepaper/validate', [RegTechController::class, 'validateWhitepaper'])->middleware('throttle:10,1')->name('mica.whitepaper.validate');
    Route::get('/mica/reserves', [RegTechController::class, 'micaReserves'])->name('mica.reserves');

    // Travel Rule
    Route::post('/travel-rule/check', [RegTechController::class, 'travelRuleCheck'])->name('travel-rule.check');
    Route::get('/travel-rule/thresholds', [RegTechController::class, 'travelRuleThresholds'])->name('travel-rule.thresholds');
});
