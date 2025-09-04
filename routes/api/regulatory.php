<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\ComplianceAlertController;
use App\Http\Controllers\Api\ComplianceCaseController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\RegulatoryReportingController;
use App\Http\Controllers\Api\TransactionMonitoringController;
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

        // Compliance Alerts
        Route::get('/alerts', [ComplianceAlertController::class, 'index']);
        Route::get('/alerts/statistics', [ComplianceAlertController::class, 'statistics']);
        Route::get('/alerts/trends', [ComplianceAlertController::class, 'trends']);
        Route::post('/alerts', [ComplianceAlertController::class, 'store']);
        Route::post('/alerts/link', [ComplianceAlertController::class, 'linkAlerts']);
        Route::post('/alerts/create-case', [ComplianceAlertController::class, 'createCase']);
        Route::post('/alerts/search', [ComplianceAlertController::class, 'search']);
        Route::get('/alerts/{id}', [ComplianceAlertController::class, 'show']);
        Route::put('/alerts/{id}/status', [ComplianceAlertController::class, 'updateStatus']);
        Route::put('/alerts/{id}/assign', [ComplianceAlertController::class, 'assign']);
        Route::post('/alerts/{id}/notes', [ComplianceAlertController::class, 'addNote']);

        // Compliance Cases
        Route::get('/cases', [ComplianceCaseController::class, 'index']);
        Route::get('/cases/{id}', [ComplianceCaseController::class, 'show']);
        Route::post('/cases', [ComplianceCaseController::class, 'store']);
        Route::put('/cases/{id}', [ComplianceCaseController::class, 'update']);
        Route::put('/cases/{id}/assign', [ComplianceCaseController::class, 'assign']);
        Route::post('/cases/{id}/evidence', [ComplianceCaseController::class, 'addEvidence']);
        Route::post('/cases/{id}/notes', [ComplianceCaseController::class, 'addNote']);
        Route::post('/cases/{id}/escalate', [ComplianceCaseController::class, 'escalate']);
        Route::get('/cases/{id}/timeline', [ComplianceCaseController::class, 'timeline']);
        Route::delete('/cases/{id}', [ComplianceCaseController::class, 'destroy']);
    });

    // Transaction Monitoring
    Route::prefix('transaction-monitoring')->group(function () {
        Route::get('/', [TransactionMonitoringController::class, 'getMonitoredTransactions']);
        Route::get('/{id}', [TransactionMonitoringController::class, 'getTransactionDetails']);
        Route::post('/{id}/flag', [TransactionMonitoringController::class, 'flagTransaction']);
        Route::post('/{id}/clear', [TransactionMonitoringController::class, 'clearTransaction']);
        Route::get('/rules', [TransactionMonitoringController::class, 'getRules']);
        Route::post('/rules', [TransactionMonitoringController::class, 'createRule']);
        Route::put('/rules/{id}', [TransactionMonitoringController::class, 'updateRule']);
        Route::delete('/rules/{id}', [TransactionMonitoringController::class, 'deleteRule']);
        Route::get('/patterns', [TransactionMonitoringController::class, 'getPatterns']);
        Route::get('/thresholds', [TransactionMonitoringController::class, 'getThresholds']);
        Route::put('/thresholds', [TransactionMonitoringController::class, 'updateThresholds']);
        Route::post('/analyze/realtime', [TransactionMonitoringController::class, 'analyzeRealtime']);
        Route::post('/analyze/batch', [TransactionMonitoringController::class, 'analyzeBatch']);
        Route::get('/analysis/{analysisId}', [TransactionMonitoringController::class, 'getAnalysisStatus']);
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
