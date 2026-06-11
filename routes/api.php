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

// Monitoring endpoints. Health probes stay public (Kubernetes liveness/readiness);
// metrics endpoints are gated (token or IP allowlist) — they expose business metrics.
Route::prefix('monitoring')->group(function () {
    Route::get('/metrics', [App\Http\Controllers\Api\MonitoringController::class, 'prometheus'])
        ->middleware(App\Http\Middleware\MetricsAccessMiddleware::class)
        ->name('monitoring.metrics');
    Route::get('/prometheus', [App\Http\Controllers\Api\MonitoringController::class, 'prometheus'])
        ->middleware(App\Http\Middleware\MetricsAccessMiddleware::class)
        ->name('monitoring.prometheus');
    Route::get('/health', [App\Http\Controllers\Api\MonitoringController::class, 'health'])->name('monitoring.health');
    Route::get('/ready', [App\Http\Controllers\Api\MonitoringController::class, 'ready'])->name('monitoring.ready');
    Route::get('/alive', [App\Http\Controllers\Api\MonitoringController::class, 'alive'])->name('monitoring.alive');
});

// Domain metrics endpoint for Prometheus scraping (gated: token or IP allowlist)
Route::get('/metrics/prometheus', [App\Http\Controllers\Api\MetricsController::class, 'prometheus'])
    ->middleware(App\Http\Middleware\MetricsAccessMiddleware::class)
    ->name('metrics.prometheus');
Route::get('/health', [App\Http\Controllers\Api\MetricsController::class, 'health'])->name('health.quick');

// WebSocket configuration endpoints (public - for client initialization)
Route::prefix('websocket')->name('api.websocket.')->group(function () {
    Route::get('/config', [App\Http\Controllers\Api\WebSocketController::class, 'config'])->name('config');
    Route::get('/status', [App\Http\Controllers\Api\WebSocketController::class, 'status'])->name('status');
    Route::get('/channels/{type}', [App\Http\Controllers\Api\WebSocketController::class, 'channelInfo'])->name('channel-info');
});

// WebSocket authenticated endpoints
Route::prefix('websocket')->name('api.websocket.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/channels', [App\Http\Controllers\Api\WebSocketController::class, 'channels'])->name('channels');
        Route::get('/subscriptions', [App\Http\Controllers\Api\WebSocket\PaidChannelController::class, 'index'])->name('subscriptions.index');
        Route::delete('/subscriptions/{id}', [App\Http\Controllers\Api\WebSocket\PaidChannelController::class, 'destroy'])->name('subscriptions.destroy');
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
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [LoginController::class, 'logout']);
        Route::post('/logout-all', [LoginController::class, 'logoutAll']);
        Route::get('/user', [LoginController::class, 'user'])->withoutMiddleware('api.rate_limit:auth')->middleware('api.rate_limit:query');
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

        // Passkey registration (requires auth)
        Route::post('/passkey/register', [PasskeyController::class, 'register'])
            ->middleware('throttle:5,1')
            ->name('api.auth.passkey.register');
    });

    // Passkey aliases (public — authentication endpoints)
    Route::prefix('passkey')->middleware('throttle:5,1')->group(function () {
        Route::post('/challenge', [PasskeyController::class, 'challenge'])->name('api.auth.passkey.challenge');
        Route::get('/challenge', [PasskeyController::class, 'challenge'])->name('api.auth.passkey.challenge.get');
        Route::post('/verify', [PasskeyController::class, 'authenticate'])->name('api.auth.passkey.verify');
        Route::post('/authenticate', [PasskeyController::class, 'authenticate']);
    });
});

// User profile (avatar upload/delete)
Route::prefix('v1/users')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/avatar', [App\Http\Controllers\Api\UserProfileController::class, 'uploadAvatar'])->middleware('throttle:10,1')->name('api.users.avatar.upload');
    Route::delete('/avatar', [App\Http\Controllers\Api\UserProfileController::class, 'deleteAvatar'])->middleware('api.rate_limit:query')->name('api.users.avatar.delete');
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
})->middleware(['auth:sanctum', 'deprecated:2026-09-01']);

// Legacy KYC documents endpoint for backward compatibility
Route::middleware(['auth:sanctum', 'deprecated:2026-09-01'])->post('/kyc/documents', [KycController::class, 'upload']);

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

// Stripe KYC payment webhook (signature-verified, no auth)
Route::post('webhooks/stripe/kyc', [App\Http\Controllers\Api\Webhook\StripeKycWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.webhooks.stripe.kyc');

// Plan B Slice 1 — Stripe subscription webhook (separate from /stripe/webhook
// which handles CGO + KYC). Signature verified inside the controller. Dedup
// via processed_webhook_events on Stripe `event.id`.
Route::post('webhooks/stripe/subscriptions', [App\Domain\Subscription\Webhooks\SubscriptionWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.webhooks.stripe.subscriptions');

// Plan B Slice 2 — IAP webhook receivers (Apple App Store Server Notifications V2
// + Google Play Real-Time Developer Notifications). No Sanctum auth — the
// controllers verify Apple JWS / Google Pub/Sub bearer JWT internally. Both
// MUST return 200 even on processing errors (the stores retry non-2xx
// indefinitely; the controllers log and acknowledge).
Route::post('webhooks/apple/notifications', [App\Domain\Subscription\Webhooks\AppleNotificationsWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.webhooks.apple.notifications');

Route::post('webhooks/google/play', [App\Domain\Subscription\Webhooks\GooglePlayWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.webhooks.google.play');

// Plan B Slice 1 — Subscription module endpoints (Stripe-only); Slice 2 adds
// the IAP /verify endpoint under the same prefix.
Route::prefix('v1/subscription')->name('api.v1.subscription.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/me', [App\Domain\Subscription\Http\Controllers\SubscriptionController::class, 'me'])
            ->name('me');

        Route::middleware(['idempotency.required'])->group(function () {
            Route::post('/checkout', [App\Domain\Subscription\Http\Controllers\SubscriptionController::class, 'checkout'])
                ->name('checkout');
            Route::post('/change-plan', [App\Domain\Subscription\Http\Controllers\SubscriptionController::class, 'changePlan'])
                ->name('change-plan');
            Route::post('/cancel', [App\Domain\Subscription\Http\Controllers\SubscriptionController::class, 'cancel'])
                ->name('cancel');
            Route::post('/reactivate', [App\Domain\Subscription\Http\Controllers\SubscriptionController::class, 'reactivate'])
                ->name('reactivate');

            // Slice 2 — mobile P0 endpoint: server-side validate Apple/Google
            // store receipts and create / update the iap_subscriptions row.
            // Throttled per-user: receipt verification fans out to Apple/Google
            // and writes revenue rows — 10/min is ample for legitimate clients.
            Route::post('/iap/verify', [App\Domain\Subscription\Http\Controllers\IapVerifyController::class, 'verify'])
                ->middleware('throttle:10,1')
                ->name('iap.verify');
        });
    });

// Plan B Slice 3 — Pricing quote endpoints.
// POST /api/v1/pricing/quote — idempotency.required is DECIDED (OD-1): both
//   the idempotency.required middleware and the entity-key dedup coexist per Q2.1.
// GET  /api/v1/pricing/quote/{quoteId} — read-only; no idempotency middleware.
Route::prefix('v1/pricing')->name('api.v1.pricing.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::middleware(['idempotency.required'])->group(function () {
            Route::post('/quote', [App\Domain\Pricing\Http\Controllers\PricingController::class, 'quote'])
                ->name('quote');
        });

        Route::get('/quote/{quoteId}', [App\Domain\Pricing\Http\Controllers\PricingController::class, 'show'])
            ->name('quote.show');
    });

// Plan B Slice 4 — Cue queue endpoints.
// GET  /api/v1/me/pending-cues            — list pending cues for authenticated user
// POST /api/v1/me/cues/{cueId}/dismissed  — dismiss a cue (idempotent, requires Idempotency-Key)
// POST /api/v1/me/marketing-opt-out       — set pro_marketing_opt_out (PECR compliance)
Route::prefix('v1/me')->name('api.v1.me.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/pending-cues', [App\Domain\Subscription\Http\Controllers\CueController::class, 'pendingCues'])
            ->name('pending-cues');

        Route::middleware(['idempotency.required'])->group(function () {
            Route::post('/cues/{cueId}/dismissed', [App\Domain\Subscription\Http\Controllers\CueController::class, 'dismiss'])
                ->name('cues.dismiss');
        });

        Route::post('/marketing-opt-out', [App\Domain\Subscription\Http\Controllers\MarketingOptOutController::class, 'store'])
            ->name('marketing-opt-out');
    });

// Extended monitoring endpoints with authentication
Route::prefix('monitoring')->middleware(['auth:sanctum'])->group(function () {
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
Route::prefix('v1/monitoring/live-dashboard')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [App\Http\Controllers\Api\V1\LiveDashboardController::class, 'index']);
    Route::get('/domain-health', [App\Http\Controllers\Api\V1\LiveDashboardController::class, 'domainHealth']);
    Route::get('/event-throughput', [App\Http\Controllers\Api\V1\LiveDashboardController::class, 'eventThroughput']);
    Route::get('/stream-status', [App\Http\Controllers\Api\V1\LiveDashboardController::class, 'streamStatus']);
    Route::get('/projector-lag', [App\Http\Controllers\Api\V1\LiveDashboardController::class, 'projectorLag']);
});

// Admin dashboard endpoint (with 2FA requirement)
Route::prefix('admin')->middleware(['auth:sanctum', 'require.2fa.admin'])->group(function () {
    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Admin dashboard',
            'user'    => auth()->user(),
        ]);
    });
});

// Privy login — exchange a Privy session JWT for a Sanctum token (public, no auth)
Route::prefix('v1/auth')
    ->middleware('api.rate_limit:auth')
    ->group(function () {
        Route::post('/privy-login', [LoginController::class, 'privyLogin'])
            ->name('api.v1.auth.privy-login');
    });

// Passkey/WebAuthn Authentication (v2.7.0) - public assertion flow
Route::prefix('v1/auth/passkey')
    ->middleware('throttle:5,1')
    ->name('mobile.auth.passkey.')
    ->group(function () {
        Route::post('/challenge', [PasskeyController::class, 'challenge'])->name('challenge');
        Route::post('/authenticate', [PasskeyController::class, 'authenticate'])->name('authenticate');
    });

// Passkey registration (requires auth) - v1 path
Route::prefix('v1/auth/passkey')
    ->middleware(['auth:sanctum', 'throttle:5,1'])
    ->name('mobile.auth.passkey.authed.')
    ->group(function () {
        Route::post('/register-challenge', [PasskeyController::class, 'challenge'])->name('register-challenge');
        Route::post('/register', [PasskeyController::class, 'register'])->name('register');
    });

// v5.13.0 — Banners (promotional carousel)
Route::prefix('v1/banners')->name('api.v1.banners.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/', [App\Http\Controllers\Api\V1\BannerController::class, 'index'])->name('index');
        Route::post('/{id}/dismiss', [App\Http\Controllers\Api\V1\BannerController::class, 'dismiss'])->name('dismiss');
    });

// v5.13.0 — On/Off Ramp
Route::prefix('v1/ramp')->name('api.v1.ramp.')
    ->middleware(['auth:sanctum', 'require.kyc'])
    ->group(function () {
        Route::get('/supported', [App\Http\Controllers\Api\V1\RampController::class, 'supported'])->middleware('api.rate_limit:query')->name('supported');
        Route::get('/quotes', [App\Http\Controllers\Api\V1\RampController::class, 'quotes'])->middleware('api.rate_limit:query')->name('quotes');
        Route::post('/session', [App\Http\Controllers\Api\V1\RampController::class, 'createSession'])->name('session.create');
        Route::get('/session/{id}', [App\Http\Controllers\Api\V1\RampController::class, 'getSession'])->name('session.show');
        Route::get('/sessions', [App\Http\Controllers\Api\V1\RampController::class, 'listSessions'])->name('sessions');
    });

// v5.13.0 — Ramp Webhooks (no auth, HMAC verified)
Route::post('v1/ramp/webhook/{provider}', [App\Http\Controllers\Api\V1\RampWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.v1.ramp.webhook');

// Bridge.xyz dedicated webhook (no auth, HMAC verified) — handles both
// customer.kyc_link_* and virtual_account.* / transfer.* events. See
// docs/BACKEND_HANDOVER_BRIDGE_RAMP.md §3.1. Configure Bridge dashboard
// to POST here.
Route::post('v1/webhooks/bridge', [App\Http\Controllers\Api\V1\BridgeWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.v1.webhooks.bridge');

// Bridge.xyz setup (KYC + virtual account provisioning) — distinct from
// /v1/ramp/* because setup is per-user and one-time. No require.kyc middleware
// here: these endpoints are how you START Bridge KYC. See ADR-0005.
Route::prefix('v1/user')->name('api.v1.user.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/bridge-setup-status', [App\Http\Controllers\Api\V1\BridgeSetupController::class, 'status'])
            ->middleware('api.rate_limit:query')
            ->name('bridge-setup-status');
        Route::post('/bridge-kyc-link', [App\Http\Controllers\Api\V1\BridgeSetupController::class, 'kycLink'])
            ->name('bridge-kyc-link');
        Route::post('/bridge-va-provision', [App\Http\Controllers\Api\V1\BridgeSetupController::class, 'provisionVirtualAccount'])
            ->name('bridge-va-provision');
    });

// v5.14.0 — Alchemy Address Activity Webhook (no auth, HMAC verified)
Route::post('webhooks/alchemy/address-activity', [App\Http\Controllers\Api\Webhook\AlchemyWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.webhooks.alchemy.address-activity');

// Helius Solana webhook (secret verified via Authorization header)
Route::post('webhooks/helius/solana', [App\Http\Controllers\Api\Webhook\HeliusWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.webhooks.helius.solana');

// HyperSwitch payment lifecycle webhook (HMAC-SHA512 verified)
Route::post('webhooks/hyperswitch', [App\Http\Controllers\Api\Webhook\HyperSwitchWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.webhooks.hyperswitch');

// Visa CLI payment status webhook (no auth, HMAC verified)
Route::post('webhooks/visa-cli/payment', [App\Http\Controllers\Api\Webhook\VisaCliWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.webhooks.visacli.payment');

// v5.13.0 — Referral System
Route::prefix('v1/referrals')->name('api.v1.referrals.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/my-code', [App\Http\Controllers\Api\V1\ReferralController::class, 'myCode'])->name('my-code');
        Route::post('/apply', [App\Http\Controllers\Api\V1\ReferralController::class, 'apply'])->name('apply');
        Route::get('/stats', [App\Http\Controllers\Api\V1\ReferralController::class, 'stats'])->name('stats');
        Route::get('/', [App\Http\Controllers\Api\V1\ReferralController::class, 'index'])->name('index');
    });

// v5.13.0 — Gas Sponsorship status
Route::prefix('v1/sponsorship')->name('api.v1.sponsorship.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/status', [App\Http\Controllers\Api\V1\SponsorshipController::class, 'status'])->name('status');
    });

// Card pre-order waitlist
Route::prefix('v1/cards/waitlist')->name('api.v1.cards.waitlist.')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/', [App\Http\Controllers\Api\V1\CardWaitlistController::class, 'join'])->name('join');
    Route::get('/status', [App\Http\Controllers\Api\V1\CardWaitlistController::class, 'status'])->name('status');
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
