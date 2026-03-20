<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Partner\PartnerBillingController;
use App\Http\Controllers\Api\Partner\PartnerDashboardController;
use App\Http\Controllers\Api\Partner\PartnerMarketplaceController;
use App\Http\Controllers\Api\Partner\PartnerSdkController;
use App\Http\Controllers\Api\Partner\PartnerWidgetController;
use Illuminate\Support\Facades\Route;

// BaaS Partner API Routes (v2.9.0)
Route::prefix('partner/v1')->name('api.partner.')->middleware('partner.auth')->group(function () {
    // Dashboard
    Route::get('/profile', [PartnerDashboardController::class, 'profile'])->name('profile');
    Route::get('/usage', [PartnerDashboardController::class, 'usage'])->name('usage');
    Route::get('/usage/history', [PartnerDashboardController::class, 'usageHistory'])->name('usage.history');
    Route::get('/tier', [PartnerDashboardController::class, 'tier'])->name('tier');
    Route::get('/tier/comparison', [PartnerDashboardController::class, 'tierComparison'])->name('tier.comparison');
    Route::get('/branding', [PartnerDashboardController::class, 'branding'])->name('branding');
    Route::put('/branding', [PartnerDashboardController::class, 'updateBranding'])->name('branding.update');

    // SDK
    Route::get('/sdk/languages', [PartnerSdkController::class, 'languages'])->name('sdk.languages');
    Route::post('/sdk/generate', [PartnerSdkController::class, 'generate'])->name('sdk.generate');
    Route::get('/sdk/openapi-spec', [PartnerSdkController::class, 'openapiSpec'])->name('sdk.openapi-spec');
    Route::get('/sdk/{language}', [PartnerSdkController::class, 'status'])->name('sdk.status');

    // Widgets
    Route::get('/widgets', [PartnerWidgetController::class, 'index'])->name('widgets.index');
    Route::post('/widgets/{type}/embed', [PartnerWidgetController::class, 'embed'])->name('widgets.embed');
    Route::get('/widgets/{type}/preview', [PartnerWidgetController::class, 'preview'])->name('widgets.preview');

    // Billing
    Route::get('/billing/invoices', [PartnerBillingController::class, 'invoices'])->name('billing.invoices');
    Route::get('/billing/invoices/{id}', [PartnerBillingController::class, 'invoice'])->name('billing.invoice');
    Route::post('/billing/invoices/{id}/pay', [PartnerBillingController::class, 'payInvoice'])->name('billing.invoice.pay');
    Route::get('/billing/outstanding', [PartnerBillingController::class, 'outstanding'])->name('billing.outstanding');
    Route::get('/billing/breakdown', [PartnerBillingController::class, 'breakdown'])->name('billing.breakdown');

    // Marketplace
    Route::get('/marketplace', [PartnerMarketplaceController::class, 'index'])->name('marketplace.index');
    Route::get('/marketplace/integrations', [PartnerMarketplaceController::class, 'integrations'])->name('marketplace.integrations');
    Route::post('/marketplace/integrations', [PartnerMarketplaceController::class, 'enable'])->name('marketplace.integrations.enable');
    Route::delete('/marketplace/integrations/{id}', [PartnerMarketplaceController::class, 'disable'])->name('marketplace.integrations.disable');
    Route::post('/marketplace/integrations/{id}/test', [PartnerMarketplaceController::class, 'test'])->name('marketplace.integrations.test');
    Route::get('/marketplace/health', [PartnerMarketplaceController::class, 'health'])->name('marketplace.health');
});
