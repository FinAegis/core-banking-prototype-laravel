<?php

/**
 * SubscriptionWebhookController — Plan B Slice 1.
 *
 * Mounted at POST /webhooks/stripe/subscriptions, distinct from the existing
 * Cashier route at POST /stripe/webhook (which handles CGO + KYC webhooks via
 * App\Http\Controllers\StripeWebhookController). The two routes are siblings,
 * not extensions, so the existing CGO/KYC handlers are not regressed.
 *
 * Side-effects table (Backend-Q1 #6):
 *
 *   checkout.session.completed     → setup-mode only: fetch PM fingerprint,
 *                                    run trial-abuse gate, create Subscription
 *                                    if eligible (Backend-Q5 #1c step 4)
 *   customer.subscription.created  → consent log (if metadata present)
 *                                  → revenue_outbox_events (subscription_initial)
 *   invoice.payment_succeeded      → revenue_outbox_events (subscription_renewal),
 *                                    suppress active payment_failed cue (slice 4)
 *   invoice.payment_failed         → cue (slice 4 stub for now)
 *   customer.subscription.updated  → projection refresh (logged; Cashier handles
 *                                    stored row sync via its own listener)
 *   customer.subscription.deleted  → mark inactive (Cashier already does this)
 *   charge.refunded                → revenue_outbox_events (refund)
 *
 * Idempotent on Stripe `event.id` via processed_webhook_events.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Webhooks;

use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Domain\Subscription\Models\SubscriptionConsentLog;
use App\Domain\Subscription\Services\SubscriptionService;
use App\Domain\Subscription\Services\TrialFingerprintService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;

final class SubscriptionWebhookController
{
    public function __construct(
        private readonly SubscriptionService $service,
        private readonly TrialFingerprintService $trialFingerprints,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = (string) $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');
        $secret = (string) (
            config('services.stripe.subscription_webhook_secret')
            ?? config('services.stripe.webhook_secret')
        );

        $event = $this->verifySignature($payload, $signature, $secret);

        if ($event === null) {
            return response()->json(['code' => 'invalid_signature'], 400);
        }

        $eventId = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['type'] ?? '');

        if ($eventId === '' || $eventType === '') {
            return response()->json(['code' => 'invalid_event'], 400);
        }

        // Phase 1 — atomic dedup claim. The (provider, event_id) dedup row write
        // AND side-effects that must be atomic with it (consent log + outbox row
        // writes from dispatchEvent) are inside a single transaction. If
        // dispatchEvent throws, the dedup row is rolled back so Stripe's
        // redeliver retries successfully instead of silently losing data.
        //
        // Note: onCheckoutSessionCompleted calls live Stripe APIs internally and
        // catches all Throwable — it will not bubble exceptions that would roll
        // back the dedup row unintentionally.
        /** @var array<string, mixed> $result */
        $result = ['replayed' => false];

        try {
            /** @var array{replayed: bool} $txResult */
            $txResult = DB::transaction(function () use ($event, $eventId, $eventType): array {
                $existing = ProcessedWebhookEvent::query()
                    ->where('provider', 'stripe')
                    ->where('event_id', $eventId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return ['replayed' => true];
                }

                ProcessedWebhookEvent::query()->create([
                    'provider'     => 'stripe',
                    'event_id'     => $eventId,
                    'event_type'   => $eventType,
                    'processed_at' => now(),
                ]);

                /** @var array<string, mixed> $object */
                $object = (array) ($event['data']['object'] ?? []);
                $this->dispatchEvent($eventType, $object, $eventId);

                return ['replayed' => false];
            });

            $result = $txResult;
        } catch (Throwable $e) {
            Log::error('subscription.webhook.failed', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
            ]);

            return response()->json(['code' => 'handler_error'], 500);
        }

        return response()->json([
            'received' => true,
            'replayed' => $result['replayed'],
            'event_id' => $eventId,
        ]);
    }

    /**
     * @param array<string, mixed> $object  the `data.object` from the Stripe event
     */
    private function dispatchEvent(string $eventType, array $object, string $eventId): void
    {
        match ($eventType) {
            'customer.subscription.created' => $this->onSubscriptionCreated($object, $eventId),
            'invoice.payment_succeeded'     => $this->onInvoicePaymentSucceeded($object, $eventId),
            'invoice.payment_failed'        => $this->onInvoicePaymentFailed($object, $eventId),
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->onSubscriptionLifecycle($object, $eventId, $eventType),
            'charge.refunded'               => $this->onChargeRefunded($object, $eventId),
            'checkout.session.completed'    => $this->onCheckoutSessionCompleted($object, $eventId),
            default                         => Log::info('subscription.webhook.unhandled', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
            ]),
        };
    }

    /**
     * customer.subscription.created — write consent log + outbox row.
     *
     * @param array<string, mixed> $subscription
     */
    private function onSubscriptionCreated(array $subscription, string $eventId): void
    {
        $stripeCustomerId = (string) ($subscription['customer'] ?? '');
        $user = $stripeCustomerId !== '' ? $this->resolveUserByStripeCustomer($stripeCustomerId) : null;

        if ($user instanceof User) {
            $this->writeConsentLogIfMetadataPresent($user, $subscription);
        }

        $payload = [
            'userId'       => $user?->id,
            'aggregateId'  => (string) ($subscription['id'] ?? ''),
            'amount'       => $this->extractAmountFromSubscription($subscription),
            'decimals'     => 2,
            'denomination' => $this->extractCurrencyFromSubscription($subscription),
            'emittedAt'    => now()->toIso8601String(),
            'rawType'      => 'customer.subscription.created',
        ];

        $this->service->enqueueRevenueOutbox(
            sourceType: RevenueOutboxEvent::SOURCE_STRIPE,
            eventId: $eventId,
            eventKind: 'customer.subscription.created',
            payload: $payload,
        );
    }

    /**
     * @param array<string, mixed> $invoice
     */
    private function onInvoicePaymentSucceeded(array $invoice, string $eventId): void
    {
        $stripeCustomerId = (string) ($invoice['customer'] ?? '');
        $user = $stripeCustomerId !== '' ? $this->resolveUserByStripeCustomer($stripeCustomerId) : null;

        $payload = [
            'userId'       => $user?->id,
            'aggregateId'  => (string) ($invoice['subscription'] ?? $invoice['id'] ?? ''),
            'amount'       => (int) ($invoice['amount_paid'] ?? 0),
            'decimals'     => 2,
            'denomination' => strtoupper((string) ($invoice['currency'] ?? 'eur')),
            'emittedAt'    => now()->toIso8601String(),
            'rawType'      => 'invoice.payment_succeeded',
        ];

        $this->service->enqueueRevenueOutbox(
            sourceType: RevenueOutboxEvent::SOURCE_STRIPE,
            eventId: $eventId,
            eventKind: 'invoice.payment_succeeded',
            payload: $payload,
        );
    }

    /**
     * @param array<string, mixed> $invoice
     */
    private function onInvoicePaymentFailed(array $invoice, string $eventId): void
    {
        // Cue dispatch is slice-4 territory (Backend-Q8). For slice 1 we just log.
        Log::info('subscription.invoice_payment_failed', [
            'event_id' => $eventId,
            'invoice'  => $invoice['id'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $subscription
     */
    private function onSubscriptionLifecycle(array $subscription, string $eventId, string $eventType): void
    {
        Log::info('subscription.lifecycle', [
            'event_id'   => $eventId,
            'event_type' => $eventType,
            'sub'        => $subscription['id'] ?? null,
            'status'     => $subscription['status'] ?? null,
        ]);

        // Cashier already keeps subscriptions/items rows in sync via its own webhook
        // listener — we don't double-write. Slice 4 wires the cue side-effects.
    }

    /**
     * @param array<string, mixed> $charge
     */
    private function onChargeRefunded(array $charge, string $eventId): void
    {
        $stripeCustomerId = (string) ($charge['customer'] ?? '');
        $user = $stripeCustomerId !== '' ? $this->resolveUserByStripeCustomer($stripeCustomerId) : null;

        $payload = [
            'userId'      => $user?->id,
            'aggregateId' => (string) ($charge['id'] ?? ''),
            // Negative for refunds per ADR-0004 sign-prefix rule.
            'amount'       => -1 * (int) ($charge['amount_refunded'] ?? 0),
            'decimals'     => 2,
            'denomination' => strtoupper((string) ($charge['currency'] ?? 'eur')),
            'emittedAt'    => now()->toIso8601String(),
            'rawType'      => 'charge.refunded',
        ];

        $this->service->enqueueRevenueOutbox(
            sourceType: RevenueOutboxEvent::SOURCE_STRIPE,
            eventId: $eventId,
            eventKind: 'charge.refunded',
            payload: $payload,
        );
    }

    /**
     * checkout.session.completed — Setup-mode checkout finished (Backend-Q5 #1c).
     *
     * Steps:
     *  1. Fetch the captured PaymentMethod fingerprint via Stripe SDK.
     *  2. Run TrialFingerprintService::isEligible() — on ineligible, abort.
     *  3. On eligible: write the fingerprint row (claim).
     *  4. Create the Stripe Subscription explicitly via Cashier with trialDays(7).
     *  5. The subsequent `customer.subscription.created` webhook hits
     *     onSubscriptionCreated() which writes consent_log + outbox row.
     *
     * @param array<string, mixed> $session
     */
    private function onCheckoutSessionCompleted(array $session, string $eventId): void
    {
        $mode = (string) ($session['mode'] ?? '');
        if ($mode !== 'setup') {
            // CGO/KYC checkouts handled by the existing StripeWebhookController.
            return;
        }

        $customerId = (string) ($session['customer'] ?? '');
        $user = $customerId !== '' ? $this->resolveUserByStripeCustomer($customerId) : null;
        if (! $user instanceof User) {
            Log::info('subscription.checkout.session_completed.no_user', [
                'event_id' => $eventId,
                'customer' => $customerId,
            ]);

            return;
        }

        $paymentMethodId = $this->resolvePaymentMethodIdFromSession($session);
        if ($paymentMethodId === null) {
            Log::info('subscription.checkout.session_completed.no_pm', [
                'event_id' => $eventId,
                'user_id'  => $user->id,
            ]);

            return;
        }

        $stripe = $this->stripeClient();

        // 1. Fetch the captured PM fingerprint.
        $fingerprint = $this->fetchCardFingerprint($stripe, $paymentMethodId);
        if ($fingerprint === null) {
            Log::info('subscription.checkout.session_completed.no_fingerprint', [
                'event_id' => $eventId,
                'user_id'  => $user->id,
            ]);

            return;
        }

        $fingerprintHash = $this->trialFingerprints->hash($fingerprint);

        // 2. Trial-abuse gate.
        $eligibleAfter = $this->trialFingerprints->eligibleAfter($fingerprintHash);
        if ($eligibleAfter !== null) {
            Log::warning('subscription.checkout.trial_blocked', [
                'event_id'       => $eventId,
                'user_id'        => $user->id,
                'eligible_after' => $eligibleAfter->toIso8601String(),
                'payment_method' => $paymentMethodId,
            ]);

            // Cancel the SetupIntent gracefully and notify ops; user-facing
            // surface is the `/me` projection (no subscription created).
            $this->detachPaymentMethodSafely($stripe, $paymentMethodId);

            return;
        }

        // 3. Claim the fingerprint.
        $this->trialFingerprints->recordClaim($fingerprintHash, $user, $paymentMethodId);

        // 4. Create the Subscription explicitly (Backend-Q5 #1c step 4).
        /** @var array<string, mixed> $metadata */
        $metadata = (array) ($session['metadata'] ?? []);
        $plan = (string) ($metadata['plan'] ?? '');
        $priceId = $this->service->priceIdForPlan($plan);
        if ($priceId === null) {
            Log::warning('subscription.checkout.unknown_plan', [
                'event_id' => $eventId,
                'plan'     => $plan,
            ]);

            return;
        }

        try {
            $user->newSubscription('default', $priceId)
                ->trialDays(7)
                ->create($paymentMethodId, [], [
                    'metadata' => $metadata,
                ]);
        } catch (Throwable $e) {
            Log::error('subscription.checkout.subscription_create_failed', [
                'event_id' => $eventId,
                'user_id'  => $user->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pull the payment_method id out of a checkout.session.completed payload.
     * The shape can vary depending on whether Stripe expanded the SetupIntent.
     *
     * @param array<string, mixed> $session
     */
    private function resolvePaymentMethodIdFromSession(array $session): ?string
    {
        if (isset($session['setup_intent']) && is_array($session['setup_intent'])) {
            $pm = $session['setup_intent']['payment_method'] ?? null;
            if (is_string($pm) && $pm !== '') {
                return $pm;
            }
        }

        $direct = $session['payment_method'] ?? null;
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        // setup_intent as an id string — fetch it.
        if (isset($session['setup_intent']) && is_string($session['setup_intent']) && $session['setup_intent'] !== '') {
            try {
                $stripe = $this->stripeClient();
                $intent = $stripe->setupIntents->retrieve((string) $session['setup_intent']);
                $pm = $intent->payment_method ?? null;
                if (is_string($pm) && $pm !== '') {
                    return $pm;
                }
            } catch (Throwable $e) {
                Log::warning('subscription.checkout.setup_intent_fetch_failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Fetch `card.fingerprint` for the captured PM. Returns null when Stripe
     * doesn't expose it (e.g. non-card PM, sandbox quirk).
     *
     * Stripe SDK objects (Stripe\StripeObject) expose properties via __get
     * magic; `property_exists()` returns false for them. Use `isset()`.
     */
    private function fetchCardFingerprint(StripeClient $stripe, string $paymentMethodId): ?string
    {
        try {
            $pm = $stripe->paymentMethods->retrieve($paymentMethodId);

            $card = $pm->card ?? null;
            if (is_object($card) && isset($card->fingerprint)) {
                $fp = (string) $card->fingerprint;

                return $fp !== '' ? $fp : null;
            }
        } catch (Throwable $e) {
            Log::warning('subscription.fingerprint.fetch_failed', [
                'payment_method_id' => $paymentMethodId,
                'error'             => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function detachPaymentMethodSafely(StripeClient $stripe, string $paymentMethodId): void
    {
        try {
            $stripe->paymentMethods->detach($paymentMethodId);
        } catch (Throwable $e) {
            Log::info('subscription.fingerprint.detach_skipped', [
                'payment_method_id' => $paymentMethodId,
                'error'             => $e->getMessage(),
            ]);
        }
    }

    private function stripeClient(): StripeClient
    {
        // Resolve via container so the Stripe API version pin from
        // AppServiceProvider::boot is honoured.
        return app(StripeClient::class);
    }

    /**
     * @param array<string, mixed> $subscription
     */
    private function writeConsentLogIfMetadataPresent(User $user, array $subscription): void
    {
        $metadata = (array) ($subscription['metadata'] ?? []);

        $acceptedAt = $metadata['consent_accepted_at'] ?? null;
        $consentTextHash = $metadata['consent_text_hash'] ?? null;
        $version = $metadata['consent_version'] ?? null;

        if (! is_string($acceptedAt) || ! is_string($consentTextHash) || $version === null) {
            return;
        }

        $consentText = $this->resolveConsentTextForVersion((int) $version);

        $shownAt = $metadata['consent_shown_at'] ?? null;
        if (! is_string($shownAt) || $shownAt === '') {
            $shownAt = $acceptedAt;
        }

        $ipHash = is_string($metadata['remote_ip_hash'] ?? null)
            ? (string) $metadata['remote_ip_hash']
            : hash_hmac('sha256', '0.0.0.0', (string) config('app.key'));

        SubscriptionConsentLog::query()->create([
            'user_id'         => $user->id,
            'subscription_id' => null,
            'consent_text'    => $consentText,
            'consent_version' => (int) $version,
            'shown_at'        => $shownAt,
            'accepted_at'     => $acceptedAt,
            'ip_hash'         => $ipHash,
            'user_agent'      => null,
        ]);
    }

    private function resolveConsentTextForVersion(int $version): string
    {
        $versions = (array) config('subscription.consent_texts', []);

        if (isset($versions[$version]) && is_string($versions[$version])) {
            return (string) $versions[$version];
        }

        return 'I understand that my subscription begins immediately and I waive my 14-day right of withdrawal.';
    }

    private function resolveUserByStripeCustomer(string $stripeCustomerId): ?User
    {
        /** @var User|null $user */
        $user = User::query()->where('stripe_id', $stripeCustomerId)->first();

        return $user;
    }

    /**
     * @param array<string, mixed> $subscription
     */
    private function extractAmountFromSubscription(array $subscription): int
    {
        $items = (array) ($subscription['items']['data'] ?? []);
        if ($items === []) {
            return 0;
        }

        $first = (array) $items[0];
        $price = (array) ($first['price'] ?? []);

        return (int) ($price['unit_amount'] ?? 0);
    }

    /**
     * @param array<string, mixed> $subscription
     */
    private function extractCurrencyFromSubscription(array $subscription): string
    {
        $items = (array) ($subscription['items']['data'] ?? []);
        if ($items === []) {
            return 'EUR';
        }

        $first = (array) $items[0];
        $price = (array) ($first['price'] ?? []);

        return strtoupper((string) ($price['currency'] ?? 'eur'));
    }

    /**
     * Verify Stripe webhook signature. Falls back to JSON-decoding the payload
     * unsigned in local/testing environments per CLAUDE.md webhook-auth-bypass
     * pitfall (gated on env explicitly + empty secret — never `return true`).
     *
     * @return array<string, mixed>|null
     */
    private function verifySignature(string $payload, string $signature, string $secret): ?array
    {
        if (app()->environment('local', 'testing') && $secret === '') {
            try {
                $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : null;
            } catch (Throwable) {
                return null;
            }
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);

            return $event->toArray();
        } catch (Throwable $e) {
            Log::warning('subscription.webhook.signature_invalid', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
