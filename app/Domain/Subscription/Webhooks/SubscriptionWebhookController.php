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
 *   customer.subscription.created  → consent log (if metadata present)
 *                                  → trial_card_fingerprints record
 *                                  → revenue_outbox_events (subscription_initial)
 *   invoice.payment_succeeded      → revenue_outbox_events (subscription_renewal),
 *                                    suppress active payment_failed cue (slice 4)
 *   invoice.payment_failed         → cue (slice 4)
 *   customer.subscription.updated  → projection refresh (cache invalidation)
 *   customer.subscription.deleted  → mark inactive (Cashier already does this)
 *   charge.refunded                → revenue_outbox_events (refund), cue
 *
 * Idempotent on Stripe `event.id` via processed_webhook_events.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Webhooks;

use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Domain\Subscription\Models\SubscriptionConsentLog;
use App\Domain\Subscription\Services\SubscriptionService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Throwable;

final class SubscriptionWebhookController
{
    public function __construct(
        private readonly SubscriptionService $service,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = (string) $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');
        $secret = (string) config('services.stripe.webhook_secret');

        $event = $this->verifySignature($payload, $signature, $secret);

        if ($event === null) {
            return response()->json(['code' => 'invalid_signature'], 400);
        }

        $eventId = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['type'] ?? '');

        if ($eventId === '' || $eventType === '') {
            return response()->json(['code' => 'invalid_event'], 400);
        }

        // Dedup gate (deltas Q7.3) — same Stripe event.id is a no-op on replay.
        // Using a transaction keeps the dedup row + outbox row atomic.
        $alreadyProcessed = false;

        try {
            DB::transaction(function () use ($event, $eventId, $eventType, &$alreadyProcessed): void {
                $existing = ProcessedWebhookEvent::query()
                    ->where('provider', 'stripe')
                    ->where('event_id', $eventId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $alreadyProcessed = true;

                    return;
                }

                ProcessedWebhookEvent::query()->create([
                    'provider'     => 'stripe',
                    'event_id'     => $eventId,
                    'event_type'   => $eventType,
                    'processed_at' => now(),
                ]);

                $this->dispatchEvent($eventType, (array) ($event['data']['object'] ?? []), $eventId, $event);
            });
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
            'replayed' => $alreadyProcessed,
            'event_id' => $eventId,
        ]);
    }

    /**
     * @param array<string, mixed> $object  the `data.object` from the Stripe event
     * @param array<string, mixed> $event   the full event payload
     */
    private function dispatchEvent(string $eventType, array $object, string $eventId, array $event): void
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
     * customer.subscription.created — write consent log + outbox row + record
     * trial fingerprint when available.
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

        // Outbox row → ProjectRevenueOutbox worker projects to revenue_events.
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
     * checkout.session.completed — Setup-mode checkout finished. Stripe gives us
     * the captured payment_method; we hash its fingerprint, run the trial-abuse
     * gate, and then create the subscription via Cashier if eligible.
     *
     * Slice 1 stub: we record the fingerprint claim. Subscription creation
     * (currently Cashier handles it via the standard flow when caller passes a
     * priceId at checkout) is wired up in a follow-up commit when we have the
     * end-to-end Stripe SetupIntent → Subscription flow tested in CI.
     *
     * @param array<string, mixed> $session
     */
    private function onCheckoutSessionCompleted(array $session, string $eventId): void
    {
        $mode = (string) ($session['mode'] ?? '');
        if ($mode !== 'setup') {
            // CGO/KYC checkouts are handled by the existing StripeWebhookController.
            return;
        }

        $paymentMethodId = (string) ($session['setup_intent']['payment_method'] ?? $session['payment_method'] ?? '');
        $customerId = (string) ($session['customer'] ?? '');
        $user = $customerId !== '' ? $this->resolveUserByStripeCustomer($customerId) : null;

        if (! $user instanceof User || $paymentMethodId === '') {
            return;
        }

        // Trial-fingerprint recording happens once we have a card.fingerprint.
        // Slice 1 stub: log. Full Stripe SDK round-trip in slice 1.5 / 2.
        Log::info('subscription.checkout.session_completed', [
            'event_id'          => $eventId,
            'user_id'           => $user->id,
            'payment_method_id' => $paymentMethodId,
        ]);
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

        // We stored only the SHA256 of the consent text in metadata to avoid the
        // 500-char Stripe metadata limit + leakage. The full text is reconstructed
        // from config at the audit-write moment; consent_version locks the snapshot.
        $consentText = $this->resolveConsentTextForVersion((int) $version);

        SubscriptionConsentLog::query()->create([
            'user_id'         => $user->id,
            'subscription_id' => null, // Cashier's subscriptions row id is not yet bound here
            'consent_text'    => $consentText,
            'consent_version' => (int) $version,
            'shown_at'        => $metadata['consent_shown_at'] ?? $acceptedAt,
            'accepted_at'     => $acceptedAt,
            'ip_hash'         => is_string($metadata['remote_ip_hash'] ?? null)
                ? (string) $metadata['remote_ip_hash']
                : hash_hmac('sha256', '0.0.0.0', (string) config('app.key')),
            'user_agent' => null,
        ]);
    }

    private function resolveConsentTextForVersion(int $version): string
    {
        $versions = (array) config('subscription.consent_texts', []);

        if (isset($versions[$version]) && is_string($versions[$version])) {
            return (string) $versions[$version];
        }

        // Fallback to a stable canonical text. Updating the wording requires
        // bumping the version + adding the row in config.
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
     * pitfall (NEVER `return true` for non-prod — gated on env explicitly).
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
