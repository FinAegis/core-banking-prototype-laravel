<?php

/**
 * SubscriptionService — Plan B Slice 1 entry point for Stripe-only flows.
 *
 * Endpoints (POST /checkout, /change-plan, /cancel, /reactivate) call into here.
 * Each method returns either a structured success array or an array{code, ...}
 * describing an error code from config/error_codes.php — the controller maps
 * those into ErrorResponse::make() responses.
 *
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md (Backend-Q1, Q5, Q7, Q14, Q17)
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services;

use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Domain\Subscription\Projections\SubscriptionProjection;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Throwable;

final class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionProjection $projection,
        private readonly ConsentLogWriter $consent,
    ) {
    }

    /**
     * GET /api/v1/subscription/me — null-safe, returns the free-tier shape with
     * HTTP 200 when there is no subscription. Mobile P0 — see acceptance.
     *
     * @return array<string, mixed>
     */
    public function read(User $user): array
    {
        return $this->projection->for($user);
    }

    /**
     * POST /api/v1/subscription/checkout — Setup-mode checkout per Backend-Q5.
     *
     * Two pre-checks before creating a Checkout session:
     *  - Multi-store conflict gate (Backend-Q1 #8): ERR_SUB_007 if IAP active.
     *  - Live-incomplete-session 409 (deltas Q7.2): ERR_SUB_005 + recoveryUrl.
     *
     * Trial-fingerprint check (Backend-Q5) runs in the webhook on
     * `checkout.session.completed` once Stripe gives us the captured PM
     * fingerprint — see SubscriptionWebhookHandler::handleCheckoutSessionCompleted.
     *
     * @param array{
     *     plan: string,
     *     withdrawalConsent: array{
     *         given: bool, shownAt: string, acceptedAt: string,
     *         consentText: string, version: int
     *     },
     *     successUrl?: ?string,
     *     cancelUrl?: ?string
     * } $input
     *
     * @return array{code?: string, extra?: array<string, mixed>, status?: int, success?: array<string, mixed>}
     */
    public function startCheckout(User $user, array $input, string $remoteIp, ?string $userAgent): array
    {
        // Cross-source conflict (Backend-Q1 #8 — IAP gate).
        if ($this->projection->hasActiveProSubscription($user, source: 'iap')) {
            return [
                'code'  => 'ERR_SUB_007',
                'extra' => [
                    'conflict' => [
                        'kind'            => 'two_stores_active',
                        'attemptedSource' => 'stripe_web',
                        'existingSource'  => 'iap',
                    ],
                ],
            ];
        }

        // Active Stripe subscription already → 409 (caller can switch plans via change-plan).
        if ($this->projection->hasActiveProSubscription($user, source: 'stripe')) {
            return [
                'code'  => 'ERR_SUB_002',
                'extra' => [
                    'conflict' => [
                        'kind'            => 'two_stores_active',
                        'attemptedSource' => 'stripe_web',
                        'existingSource'  => 'stripe_web',
                    ],
                ],
            ];
        }

        // Withdrawal-consent staleness check (deltas Q14.1).
        if (
            ! $this->consent->isWellFormed($input['withdrawalConsent'])
            || $this->consent->isStale($input['withdrawalConsent']['acceptedAt'])
        ) {
            return ['code' => 'ERR_SUB_004'];
        }

        // Live-incomplete-session 409 (deltas Q7.2).
        $live = $this->detectLiveIncompleteSession($user);
        if ($live !== null) {
            return [
                'code'  => 'ERR_SUB_005',
                'extra' => [
                    'recoveryUrl' => $live,
                ],
            ];
        }

        // Build the Checkout session in Setup mode.
        try {
            /** @var StripeCheckoutSession $session */
            $session = $user->checkout(
                items: [],
                sessionOptions: [
                    'mode'        => 'setup',
                    'success_url' => $input['successUrl']
                        ?? (string) config('app.url') . '/subscription/checkout/success?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $input['cancelUrl']
                        ?? (string) config('app.url') . '/subscription/checkout/cancel',
                    'payment_method_types' => ['card'],
                    'metadata'             => [
                        'plan'                => $input['plan'],
                        'consent_accepted_at' => $input['withdrawalConsent']['acceptedAt'],
                        'consent_version'     => $input['withdrawalConsent']['version'],
                        'consent_text_hash'   => hash('sha256', $input['withdrawalConsent']['consentText']),
                        'remote_ip_hash'      => hash_hmac('sha256', $remoteIp, (string) config('app.key')),
                    ],
                    'customer_creation' => $user->stripe_id !== null ? null : 'always',
                ],
                customerOptions: [],
            );
        } catch (Throwable $e) {
            Log::error('subscription.checkout.create_failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            throw $e;
        }

        return [
            'success' => [
                'checkoutUrl' => (string) $session->url,
                'sessionId'   => (string) $session->id,
                'plan'        => $input['plan'],
            ],
        ];
    }

    /**
     * POST /api/v1/subscription/change-plan — Stripe-only, thin Cashier wrapper.
     *
     * Annual → Monthly downgrade rejected per deltas Q17.4 (intentional).
     *
     * @return array{code?: string, extra?: array<string, mixed>, success?: array<string, mixed>}
     */
    public function changePlan(User $user, string $plan): array
    {
        $subscription = $user->subscription('default');

        if ($subscription === null || ! $subscription->valid()) {
            // No active sub to change. Treat as a soft 422 — caller should hit /checkout.
            return [
                'code'  => 'ERR_SUB_002',
                'extra' => ['message' => 'No active subscription to change.'],
            ];
        }

        $currentPlan = $this->planFromPriceId((string) $subscription->stripe_price);

        // Annual → Monthly downgrade is intentionally not offered (deltas Q17.4).
        // A future developer adding "obvious missing feature" Switch-to-Monthly on
        // annual users undoes the retention design.
        if ($currentPlan === 'annual_pro' && $plan === 'monthly_pro') {
            return ['code' => 'ERR_SUB_006'];
        }

        // Idempotent no-op: same-plan swap returns the existing sub.
        if ($currentPlan === $plan) {
            return [
                'success' => [
                    'subscription' => $this->projection->for($user->refresh()),
                ],
            ];
        }

        $targetPriceId = $this->priceIdForPlan($plan);
        if ($targetPriceId === null) {
            return [
                'code'  => 'ERR_SUB_002',
                'extra' => ['message' => "Unknown plan: {$plan}"],
            ];
        }

        $subscription->swap($targetPriceId);

        return [
            'success' => [
                'subscription' => $this->projection->for($user->refresh()),
            ],
        ];
    }

    /**
     * POST /api/v1/subscription/cancel — cancel-at-period-end (Cashier default).
     *
     * If the active subscription is via IAP, return ERR_SUB_010 with a deep-link
     * to the App Store / Play Store subscription management — IAP is store-side
     * only (slice 2 builds the IAP detection; slice 1 simply cannot detect it
     * because there is no iap_subscriptions data yet, but the gate is wired so
     * future agents see the contract).
     *
     * @return array{code?: string, extra?: array<string, mixed>, success?: array<string, mixed>}
     */
    public function cancel(User $user): array
    {
        // Slice 1 cannot detect an IAP-only sub yet (no iap_subscriptions table).
        // The gate is wired so slice 2 plugs in.
        if ($this->projection->hasActiveProSubscription($user, source: 'iap')) {
            return [
                'code'  => 'ERR_SUB_010',
                'extra' => [
                    'managementUrl' => 'https://apps.apple.com/account/subscriptions',
                    'androidUrl'    => 'https://play.google.com/store/account/subscriptions',
                ],
            ];
        }

        $subscription = $user->subscription('default');

        if ($subscription === null || ! $subscription->valid()) {
            return [
                'code'  => 'ERR_SUB_002',
                'extra' => ['message' => 'No active subscription to cancel.'],
            ];
        }

        // Idempotent: already cancel-at-period-end → return existing.
        if ($subscription->ends_at === null) {
            $subscription->cancel();
        }

        return [
            'success' => [
                'subscription' => $this->projection->for($user->refresh()),
            ],
        ];
    }

    /**
     * POST /api/v1/subscription/reactivate — clears cancelled-at-period-end.
     *
     * @return array{code?: string, extra?: array<string, mixed>, success?: array<string, mixed>}
     */
    public function reactivate(User $user): array
    {
        $subscription = $user->subscription('default');

        if ($subscription === null) {
            return [
                'code'  => 'ERR_SUB_002',
                'extra' => ['message' => 'No subscription to reactivate.'],
            ];
        }

        if ($subscription->ends_at === null || ! Carbon::parse($subscription->ends_at)->isFuture()) {
            // Idempotent: already active and not cancel-at-period-end.
            return [
                'success' => [
                    'subscription' => $this->projection->for($user->refresh()),
                ],
            ];
        }

        $subscription->resume();

        return [
            'success' => [
                'subscription' => $this->projection->for($user->refresh()),
            ],
        ];
    }

    /**
     * Detect a live (≤24h, retrievable) Stripe Checkout session for this user.
     * Returns the recovery URL or null. Best-effort: under failure we return null
     * and let the caller create a fresh session.
     */
    private function detectLiveIncompleteSession(User $user): ?string
    {
        if ($user->stripe_id === null) {
            return null;
        }

        try {
            $stripe = new \Stripe\StripeClient((string) config('cashier.secret'));
            $sessions = $stripe->checkout->sessions->all([
                'customer' => $user->stripe_id,
                'limit'    => 5,
            ]);

            foreach ($sessions->data as $session) {
                if (! is_object($session)) {
                    continue;
                }

                $status = property_exists($session, 'status') ? (string) $session->status : '';
                $url = property_exists($session, 'url') ? (string) $session->url : '';
                $expiresAt = property_exists($session, 'expires_at') ? (int) $session->expires_at : 0;

                if ($status === 'open' && $url !== '' && $expiresAt > time()) {
                    return $url;
                }
            }
        } catch (Throwable $e) {
            Log::warning('subscription.checkout.live_session_detect_failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function priceIdForPlan(string $plan): ?string
    {
        $prices = (array) config('services.stripe.subscription_prices', []);
        $configured = $prices[$plan] ?? null;

        if (! is_string($configured) || $configured === '') {
            return null;
        }

        return $configured;
    }

    public function planFromPriceId(string $priceId): ?string
    {
        $prices = (array) config('services.stripe.subscription_prices', []);

        foreach ($prices as $plan => $configuredPrice) {
            if ((string) $configuredPrice === $priceId && $configuredPrice !== '') {
                return (string) $plan;
            }
        }

        return null;
    }

    /**
     * Used by the webhook handler to enqueue an outbox row inside the same
     * transaction as the dedup write. Idempotent on (source_type, source_event_id).
     *
     * @param array<string, mixed> $payload
     */
    public function enqueueRevenueOutbox(string $sourceType, string $eventId, string $eventKind, array $payload): RevenueOutboxEvent
    {
        /** @var RevenueOutboxEvent $row */
        $row = RevenueOutboxEvent::query()->updateOrCreate(
            [
                'source_type'     => $sourceType,
                'source_event_id' => $eventId,
            ],
            [
                'event_kind' => $eventKind,
                'payload'    => $payload,
                'status'     => RevenueOutboxEvent::STATUS_PENDING,
            ]
        );

        return $row;
    }
}
