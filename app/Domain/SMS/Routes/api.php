<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SMS\SmsController;
use App\Http\Controllers\Api\Webhook\VertexSmsDlrController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SMS Domain Routes
|--------------------------------------------------------------------------
|
| POST /v1/sms/send   — MPP-gated: send SMS, pay per-message
| GET  /v1/sms/rates   — public: check rates by country
| GET  /v1/sms/info    — public: service status
| GET  /v1/sms/status/{id} — auth: check delivery status
|
| POST /v1/webhooks/vertexsms/dlr — DLR delivery reports
|
*/

Route::prefix('v1/sms')->group(function (): void {
    // Public endpoints
    Route::get('info', [SmsController::class, 'info']);
    Route::get('rates', [SmsController::class, 'rates']);

    // MPP payment-gated endpoint
    Route::post('send', [SmsController::class, 'send'])
        ->middleware('mpp.payment');

    // Authenticated endpoints
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('status/{messageId}', [SmsController::class, 'status']);
    });
});

// Webhook endpoint (no auth — signature verified in controller)
Route::post('v1/webhooks/vertexsms/dlr', [VertexSmsDlrController::class, 'handle']);
