<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SMS\SmsController;
use App\Http\Controllers\Api\Webhook\VertexSmsDlrController;
use App\Http\Middleware\SmsSendGateMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SMS Domain Routes
|--------------------------------------------------------------------------
|
| POST /v1/sms/send       — SMS_ENABLED switch, then either an MPP payment
|                           credential (paid) or a Sanctum token with the
|                           per-user limit and the global daily cap (unpaid)
| GET  /v1/sms/rates      — public: check rates by country
| GET  /v1/sms/info       — public: service status
| GET  /v1/sms/status/{id} — auth: check delivery status
|
| POST /v1/webhooks/vertexsms/dlr — DLR delivery reports (rate-limited)
|
*/

Route::prefix('v1/sms')->group(function (): void {
    // Public endpoints
    Route::get('info', [SmsController::class, 'info']);
    Route::get('rates', [SmsController::class, 'rates']);

    // SmsSendGateMiddleware: 503 while SMS_ENABLED is off; with the MPP
    // gate on, a Payment credential pays for the send and a request with no
    // credential and no token gets the 402 challenge; otherwise Sanctum +
    // per-user limiter `sms-send` (SmsServiceProvider) + global daily cap.
    Route::post('send', [SmsController::class, 'send'])
        ->middleware([SmsSendGateMiddleware::class, 'throttle:60,1']);

    // Authenticated endpoints
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('status/{messageId}', [SmsController::class, 'status']);
    });
});

// Webhook endpoint — rate-limited, signature/URL-token verified in controller.
// Named so VertexSmsClient::buildDlrUrl() can resolve it when `sms.webhook.dlr_url` is unset.
Route::post('v1/webhooks/vertexsms/dlr', [VertexSmsDlrController::class, 'handle'])
    ->middleware('throttle:200,1')
    ->name('webhooks.vertexsms.dlr');
