<?php

use App\Http\Controllers\Api\RegulatoryReportingController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\AuditController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Regulatory Reporting API Routes
|--------------------------------------------------------------------------
|
| These routes handle regulatory reporting, compliance, and audit trails
|
*/

Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
    // Regulatory Reporting
    Route::prefix('regulatory')->group(function () {
        Route::get('/reports', [RegulatoryReportingController::class, 'getReports']);
        Route::get('/reports/{id}', [RegulatoryReportingController::class, 'getReportDetails']);
        Route::post('/reports/generate', [RegulatoryReportingController::class, 'generateReport']);
        Route::post('/reports/{id}/submit', [RegulatoryReportingController::class, 'submitReport']);
        Route::get('/reports/{id}/status', [RegulatoryReportingController::class, 'getReportStatus']);
        Route::get('/reports/{id}/download', [RegulatoryReportingController::class, 'downloadReport']);
        Route::get('/templates', [RegulatoryReportingController::class, 'getReportTemplates']);
        Route::get('/jurisdictions', [RegulatoryReportingController::class, 'getJurisdictions']);
        Route::get('/requirements', [RegulatoryReportingController::class, 'getRequirements']);
        Route::get('/deadlines', [RegulatoryReportingController::class, 'getDeadlines']);
        Route::post('/reports/{id}/amend', [RegulatoryReportingController::class, 'amendReport']);
        Route::get('/filings', [RegulatoryReportingController::class, 'getFilings']);
        Route::get('/filings/{id}', [RegulatoryReportingController::class, 'getFilingDetails']);
    });

    // Compliance Management
    Route::prefix('compliance')->group(function () {
        Route::get('/dashboard', [ComplianceController::class, 'dashboard']);
        Route::get('/violations', [ComplianceController::class, 'getViolations']);
        Route::get('/violations/{id}', [ComplianceController::class, 'getViolationDetails']);
        Route::post('/violations/{id}/resolve', [ComplianceController::class, 'resolveViolation']);
        Route::get('/rules', [ComplianceController::class, 'getComplianceRules']);
        Route::get('/rules/{jurisdiction}', [ComplianceController::class, 'getRulesByJurisdiction']);
        Route::get('/checks', [ComplianceController::class, 'getComplianceChecks']);
        Route::post('/checks/run', [ComplianceController::class, 'runComplianceCheck']);
        Route::get('/certifications', [ComplianceController::class, 'getCertifications']);
        Route::post('/certifications/renew', [ComplianceController::class, 'renewCertification']);
        Route::get('/policies', [ComplianceController::class, 'getPolicies']);
        Route::put('/policies/{id}', [ComplianceController::class, 'updatePolicy']);
    });

    // Audit Trail Management
    Route::prefix('audit')->group(function () {
        Route::get('/logs', [AuditController::class, 'getAuditLogs']);
        Route::get('/logs/export', [AuditController::class, 'exportAuditLogs']);
        Route::get('/events', [AuditController::class, 'getAuditEvents']);
        Route::get('/events/{id}', [AuditController::class, 'getEventDetails']);
        Route::get('/reports', [AuditController::class, 'getAuditReports']);
        Route::post('/reports/generate', [AuditController::class, 'generateAuditReport']);
        Route::get('/trail/{entityType}/{entityId}', [AuditController::class, 'getEntityAuditTrail']);
        Route::get('/users/{userId}/activity', [AuditController::class, 'getUserActivity']);
        Route::get('/search', [AuditController::class, 'searchAuditLogs']);
        Route::post('/archive', [AuditController::class, 'archiveAuditLogs']);
    });

    // Suspicious Activity Reporting (SAR)
    Route::prefix('sar')->group(function () {
        Route::get('/', [RegulatoryReportingController::class, 'getSARs']);
        Route::get('/{id}', [RegulatoryReportingController::class, 'getSARDetails']);
        Route::post('/create', [RegulatoryReportingController::class, 'createSAR']);
        Route::put('/{id}', [RegulatoryReportingController::class, 'updateSAR']);
        Route::post('/{id}/submit', [RegulatoryReportingController::class, 'submitSAR']);
        Route::get('/{id}/status', [RegulatoryReportingController::class, 'getSARStatus']);
        Route::post('/{id}/attach', [RegulatoryReportingController::class, 'attachDocuments']);
    });

    // Currency Transaction Reports (CTR)
    Route::prefix('ctr')->group(function () {
        Route::get('/', [RegulatoryReportingController::class, 'getCTRs']);
        Route::get('/{id}', [RegulatoryReportingController::class, 'getCTRDetails']);
        Route::post('/generate', [RegulatoryReportingController::class, 'generateCTR']);
        Route::post('/{id}/submit', [RegulatoryReportingController::class, 'submitCTR']);
        Route::get('/thresholds', [RegulatoryReportingController::class, 'getCTRThresholds']);
    });
});
