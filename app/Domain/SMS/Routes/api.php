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
| POST /v1/sms/send       — MPP-gated + rate-limited: send SMS
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

    // MPP payment-gated + rate-limited endpoint
    Route::post('send', [SmsController::class, 'send'])
        ->middleware(['mpp.payment', 'throttle:60,1']);

    // Authenticated endpoints
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('status/{messageId}', [SmsController::class, 'status']);
    });
});

// Webhook endpoint — rate-limited, signature verified in controller
Route::post('v1/webhooks/vertexsms/dlr', [VertexSmsDlrController::class, 'handle'])
    ->middleware('throttle:200,1');
