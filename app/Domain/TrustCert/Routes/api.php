<?php

declare(strict_types=1);

use App\Http\Controllers\Api\TrustCert\CertificateApplicationController;
use App\Http\Controllers\Api\TrustCert\CertificateController;
use App\Http\Controllers\Api\TrustCert\MobileTrustCertController;
use App\Http\Controllers\Api\TrustCert\PresentationController;
use App\Http\Controllers\Api\V1\TrustCertPaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/trustcert')->name('api.trustcert.')->group(function () {
    // Public verification endpoint (no auth - anyone can verify a presentation)
    Route::get('/verify/{token}', [PresentationController::class, 'verify'])->name('verify');

    // Authenticated endpoints
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/{certificateId}/present', [PresentationController::class, 'present'])->name('present');

        // Certificate details and PDF export
        Route::get('/{certId}/certificate', [CertificateController::class, 'show'])->name('certificate.show');
        Route::post('/{certId}/export-pdf', [CertificateController::class, 'exportPdf'])->name('certificate.export');
    });
});

Route::prefix('v1/trustcert')->name('mobile.trustcert.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/current', [MobileTrustCertController::class, 'current'])
            ->middleware('api.rate_limit:query')
            ->name('current');
        Route::get('/requirements', [MobileTrustCertController::class, 'requirements'])
            ->middleware('api.rate_limit:query')
            ->name('requirements');
        Route::get('/requirements/{level}', [MobileTrustCertController::class, 'requirementsByLevel'])
            ->middleware('api.rate_limit:query')
            ->name('requirements.level');
        Route::get('/limits', [MobileTrustCertController::class, 'limits'])
            ->middleware('api.rate_limit:query')
            ->name('limits');
        Route::post('/check-limit', [MobileTrustCertController::class, 'checkLimit'])
            ->middleware('api.rate_limit:query')
            ->name('check-limit');

        // Certificate applications
        Route::post('/applications', [CertificateApplicationController::class, 'create'])
            ->middleware('api.rate_limit:mutation')
            ->name('applications.create');
        Route::get('/applications/current', [CertificateApplicationController::class, 'currentApplication'])
            ->middleware('api.rate_limit:query')
            ->name('applications.current');
        Route::get('/applications/{id}', [CertificateApplicationController::class, 'show'])
            ->middleware('api.rate_limit:query')
            ->name('applications.show');
        Route::post('/applications/{id}/documents', [CertificateApplicationController::class, 'uploadDocuments'])
            ->middleware('api.rate_limit:mutation')
            ->name('applications.documents');
        Route::post('/applications/{id}/submit', [CertificateApplicationController::class, 'submit'])
            ->middleware('api.rate_limit:mutation')
            ->name('applications.submit');
        Route::post('/applications/{id}/cancel', [CertificateApplicationController::class, 'cancel'])
            ->middleware('api.rate_limit:mutation')
            ->name('applications.cancel');

        // Payment routes for paid KYC verification
        Route::post('/applications/{applicationId}/pay', [TrustCertPaymentController::class, 'payWallet'])
            ->middleware('api.rate_limit:mutation')
            ->name('applications.pay.wallet');
        Route::post('/applications/{applicationId}/pay/card', [TrustCertPaymentController::class, 'payCard'])
            ->middleware('api.rate_limit:mutation')
            ->name('applications.pay.card');
        Route::post('/applications/{applicationId}/pay/iap', [TrustCertPaymentController::class, 'payIap'])
            ->middleware('api.rate_limit:mutation')
            ->name('applications.pay.iap');
    });
