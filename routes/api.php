<?php

// SPDX-License-Identifier: Apache-2.0
// Copyright (c) 2024-2026 FinAegis Contributors

use App\Http\Controllers\Api\Auth\AccountDeletionController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\PasskeyController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\Auth\TwoFactorAuthController;
use App\Http\Controllers\Api\KycController;
use App\Infrastructure\Domain\ModuleRouteLoader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Orchestrator (v3.2.0)
|--------------------------------------------------------------------------
|
| Core routes (auth, monitoring, webhooks) are defined inline.
| Domain-specific routes are loaded from app/Domain/{Name}/Routes/api.php
| via the ModuleRouteLoader (modular architecture).
|
*/

// API root endpoint
Route::get('/', function () {
    return response()->json([
        'message'       => 'FinAegis Core Banking API',
        'version'       => 'v5',
        'documentation' => url('/api/documentation'),
        'status'        => route('status.api'),
        'endpoints'     => [
            'auth'         => url('/auth'),
            'accounts'     => url('/accounts'),
            'transactions' => url('/accounts/{uuid}/transactions'),
            'transfers'    => url('/transfers'),
            'exchange'     => url('/exchange'),
            'baskets'      => url('/baskets'),
            'stablecoins'  => url('/stablecoins'),
            'v2'           => url('/v2'),
        ],
    ]);
})->name('api.root');

// Monitoring endpoints (public - for Prometheus and Kubernetes)
Route::prefix('monitoring')->group(function () {
    Route::get('/metrics', [App\Http\Controllers\Api\MonitoringController::class, 'prometheus'])->name('monitoring.metrics');
    Route::get('/prometheus', [App\Http\Controllers\Api\MonitoringController::class, 'prometheus'])->name('monitoring.prometheus');
    Route::get('/health', [App\Http\Controllers\Api\MonitoringController::class, 'health'])->name('monitoring.health');
    Route::get('/ready', [App\Http\Controllers\Api\MonitoringController::class, 'ready'])->name('monitoring.ready');
    Route::get('/alive', [App\Http\Controllers\Api\MonitoringController::class, 'alive'])->name('monitoring.alive');
});

// WebSocket configuration endpoints (public - for client initialization)
Route::prefix('websocket')->name('api.websocket.')->group(function () {
    Route::get('/config', [App\Http\Controllers\Api\WebSocketController::class, 'config'])->name('config');
    Route::get('/status', [App\Http\Controllers\Api\WebSocketController::class, 'status'])->name('status');
    Route::get('/channels/{type}', [App\Http\Controllers\Api\WebSocketController::class, 'channelInfo'])->name('channel-info');
});

// WebSocket authenticated endpoints
Route::prefix('websocket')->name('api.websocket.')
    ->middleware(['auth:sanctum', 'check.token.expiration'])
    ->group(function () {
        Route::get('/channels', [App\Http\Controllers\Api\WebSocketController::class, 'channels'])->name('channels');
    });

// Authentication endpoints (public)
Route::prefix('auth')->middleware('api.rate_limit:auth')->group(function () {
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login']);

    // Token refresh (public — accepts refresh token in body or Authorization header)
    Route::post('/refresh', [LoginController::class, 'refresh'])->middleware('throttle:20,1');

    // Password reset endpoints (public)
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

    // Email verification endpoints
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('api.verification.verify');

    // Social authentication endpoints
    Route::get('/social/{provider}', [SocialAuthController::class, 'redirect']);
    Route::post('/social/{provider}/callback', [SocialAuthController::class, 'callback']);

    // Protected auth endpoints
    Route::middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
        Route::post('/logout', [LoginController::class, 'logout']);
        Route::post('/logout-all', [LoginController::class, 'logoutAll']);
        Route::get('/user', [LoginController::class, 'user']);
        Route::get('/me', [LoginController::class, 'user'])->name('api.auth.me');
        Route::post('/delete-account', AccountDeletionController::class)->name('api.auth.delete-account');

        // Email verification resend
        Route::post('/resend-verification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1');

        // Two-factor authentication endpoints
        Route::prefix('2fa')->group(function () {
            Route::post('/enable', [TwoFactorAuthController::class, 'enable']);
            Route::post('/confirm', [TwoFactorAuthController::class, 'confirm']);
            Route::post('/disable', [TwoFactorAuthController::class, 'disable']);
            Route::post('/verify', [TwoFactorAuthController::class, 'verify']);
            Route::post('/recovery-codes', [TwoFactorAuthController::class, 'regenerateRecoveryCodes']);
        });

        // UserOperation signing with auth shard (v2.6.0)
        Route::post('/sign-userop', [App\Http\Controllers\Api\Auth\UserOpSigningController::class, 'sign'])
            ->middleware('throttle:10,1')
            ->name('api.auth.sign-userop');

        // Passkey registration (requires auth)
        Route::post('/passkey/register', [PasskeyController::class, 'register'])
            ->middleware('throttle:5,1')
            ->name('api.auth.passkey.register');
    });

    // Passkey aliases (public — authentication endpoints)
    Route::prefix('passkey')->middleware('throttle:5,1')->group(function () {
        Route::get('/challenge', [PasskeyController::class, 'challenge'])->name('api.auth.passkey.challenge.get');
        Route::post('/challenge', [PasskeyController::class, 'challenge']);
        Route::post('/verify', [PasskeyController::class, 'authenticate'])->name('api.auth.passkey.verify');
        Route::post('/authenticate', [PasskeyController::class, 'authenticate']);
    });
});

// Legacy profile route for backward compatibility
Route::get('/profile', function (Request $request) {
    $user = $request->user();
    if (! $user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    return response()->json([
        'data' => [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'uuid'       => $user->uuid,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ],
    ]);
})->middleware(['auth:sanctum', 'check.token.expiration']);

// Legacy KYC documents endpoint for backward compatibility
Route::middleware(['auth:sanctum', 'check.token.expiration'])->post('/kyc/documents', [KycController::class, 'upload']);

// Custodian webhook endpoints (signature verification + webhook rate limiting)
Route::prefix('webhooks/custodian')->middleware(['api.rate_limit:webhook'])->group(function () {
    Route::post('/paysera', [App\Http\Controllers\Api\CustodianWebhookController::class, 'paysera'])
        ->middleware('webhook.signature:paysera');
    Route::post('/santander', [App\Http\Controllers\Api\CustodianWebhookController::class, 'santander'])
        ->middleware('webhook.signature:santander');
    Route::post('/mock', [App\Http\Controllers\Api\CustodianWebhookController::class, 'mock']);
});

// Payment processor webhook endpoints
Route::prefix('webhooks')->middleware(['api.rate_limit:webhook'])->group(function () {
    Route::post('/coinbase-commerce', [App\Http\Controllers\CoinbaseWebhookController::class, 'handleWebhook'])
        ->middleware('webhook.signature:coinbase');
});

// Ondato KYC webhook endpoints
Route::prefix('webhooks/ondato')->middleware(['api.rate_limit:webhook'])->group(function () {
    Route::post('/identity-verification', [App\Http\Controllers\Api\OndatoWebhookController::class, 'identityVerification']);
    Route::post('/identification', [App\Http\Controllers\Api\OndatoWebhookController::class, 'identification']);
});

// Extended monitoring endpoints with authentication
Route::prefix('monitoring')->middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
    Route::get('/metrics-json', [App\Http\Controllers\Api\MonitoringController::class, 'metrics']);
    Route::get('/traces', [App\Http\Controllers\Api\MonitoringController::class, 'traces']);
    Route::get('/trace/{traceId}', [App\Http\Controllers\Api\MonitoringController::class, 'trace']);
    Route::get('/alerts', [App\Http\Controllers\Api\MonitoringController::class, 'alerts']);
    Route::put('/alerts/{alertId}/acknowledge', [App\Http\Controllers\Api\MonitoringController::class, 'acknowledgeAlert']);

    Route::get('/projector-health', [App\Http\Controllers\Api\ProjectorHealthController::class, 'index']);
    Route::get('/projector-health/stale', [App\Http\Controllers\Api\ProjectorHealthController::class, 'stale']);

    Route::middleware('is_admin')->group(function () {
        Route::post('/workflow/start', [App\Http\Controllers\Api\MonitoringController::class, 'startWorkflow']);
        Route::post('/workflow/stop', [App\Http\Controllers\Api\MonitoringController::class, 'stopWorkflow']);
    });
});

// v5.0.0 — Live Dashboard
Route::prefix('v1/monitoring/live-dashboard')->middleware(['auth:sanctum', 'check.token.expiration'])->group(function () {
    Route::get('/', [App\Http\Controllers\Api\V1\LiveDashboardController::class, 'index']);
    Route::get('/domain-health', [App\Http\Controllers\Api\V1\LiveDashboardController::class, 'domainHealth']);
    Route::get('/event-throughput', [App\Http\Controllers\Api\V1\LiveDashboardController::class, 'eventThroughput']);
    Route::get('/stream-status', [App\Http\Controllers\Api\V1\LiveDashboardController::class, 'streamStatus']);
    Route::get('/projector-lag', [App\Http\Controllers\Api\V1\LiveDashboardController::class, 'projectorLag']);
});

// Admin dashboard endpoint (with 2FA requirement)
Route::prefix('admin')->middleware(['auth:sanctum', 'check.token.expiration', 'require.2fa.admin'])->group(function () {
    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Admin dashboard',
            'user'    => auth()->user(),
        ]);
    });
});

// Passkey/WebAuthn Authentication (v2.7.0) - public
Route::prefix('v1/auth/passkey')
    ->middleware('throttle:5,1')
    ->name('mobile.auth.passkey.')
    ->group(function () {
        Route::post('/challenge', [PasskeyController::class, 'challenge'])->name('challenge');
        Route::post('/authenticate', [PasskeyController::class, 'authenticate'])->name('authenticate');
    });

// Passkey registration (requires auth) - v1 path
Route::prefix('v1/auth/passkey')
    ->middleware(['auth:sanctum', 'check.token.expiration', 'throttle:5,1'])
    ->name('mobile.auth.passkey.authed.')
    ->group(function () {
        // Registration challenge (type=registration) and register endpoint
        Route::post('/challenge', [PasskeyController::class, 'challenge'])->name('challenge');
        Route::post('/register', [PasskeyController::class, 'register'])->name('register');
    });

/*
|--------------------------------------------------------------------------
| External Route Includes
|--------------------------------------------------------------------------
*/

// Include BIAN-compliant routes
require __DIR__ . '/api-bian.php';

// Include V2 public API routes
Route::prefix('v2')->middleware('ensure.json')->group(function () {
    require __DIR__ . '/api-v2.php';
});

// Include fraud detection routes
require __DIR__ . '/api/fraud.php';

// Include enhanced regulatory routes
require __DIR__ . '/api/regulatory.php';

// Include module management API routes
require __DIR__ . '/api-modules.php';

/*
|--------------------------------------------------------------------------
| Domain Module Routes (v3.2.0)
|--------------------------------------------------------------------------
|
| All domain-specific routes are loaded from their respective
| app/Domain/{Name}/Routes/api.php files via ModuleRouteLoader.
| Disabled modules have their routes automatically skipped.
| See config/modules.php for module enable/disable configuration.
|
*/

app(ModuleRouteLoader::class)->loadRoutes();
