<?php

/**
 * CardWaitlistWebhookController — Plan B Slice 5.
 *
 * Mounted at POST /webhooks/stripe/cards. Sibling to slice 1's
 * SubscriptionWebhookController — both are Stripe webhooks, both dedup on
 * Stripe `event.id`, but they target different downstream side effects and
 * use INDEPENDENT signing secrets so they can rotate independently.
 *
 * Stripe events handled (only when metadata.flow == 'card_waitlist_deposit',
 * unless the event is charge.refunded which is matched by payment_intent):
 *   checkout.session.completed  → transition to paid + write outbox row
 *   checkout.session.expired    → transition to expired
 *   charge.refunded             → transition to refunded + negative outbox row
 *
 * Idempotency: provider='stripe_cards' on processed_webhook_events. The dedup
 * INSERT and the side-effect writes happen inside ONE DB::transaction() so a
 * Stripe redelivery cannot double-fire — the slice 1 webhook bug regression
 * does not recur here.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-5-card-waitlist-deposit-design.md §5.4
 */

declare(strict_types=1);

namespace App\Domain\CardIssuance\Webhooks;

use App\Domain\CardIssuance\Services\WaitlistDepositService;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Throwable;

final class CardWaitlistWebhookController
{
    public function __construct(
        private readonly WaitlistDepositService $service,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = (string) $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');
        $secret = (string) (
            config('cards.stripe_webhook_secret')
            ?? config('services.stripe.cards_webhook_secret')
            ?? ''
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

        // Phase 1 — atomic dedup claim + side effects in a single transaction.
        /** @var array{replayed: bool} $result */
        $result = ['replayed' => false];

        try {
            /** @var array{replayed: bool} $txResult */
            $txResult = DB::transaction(function () use ($event, $eventId, $eventType): array {
                $existing = ProcessedWebhookEvent::query()
                    ->where('provider', WaitlistDepositService::WEBHOOK_PROVIDER)
                    ->where('event_id', $eventId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return ['replayed' => true];
                }

                ProcessedWebhookEvent::query()->create([
                    'provider'     => WaitlistDepositService::WEBHOOK_PROVIDER,
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
            Log::error('cards.webhook.failed', [
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
     * @param  array<string, mixed>  $object  Stripe data.object
     */
    private function dispatchEvent(string $eventType, array $object, string $eventId): void
    {
        match ($eventType) {
            'checkout.session.completed' => $this->onCheckoutCompleted($object, $eventId),
            'checkout.session.expired'   => $this->onCheckoutExpired($object, $eventId),
            'charge.refunded'            => $this->onChargeRefunded($object, $eventId),
            default                      => Log::info('cards.webhook.unhandled', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
            ]),
        };
    }

    /**
     * Only act on sessions tagged with our flow. Other Checkout sessions
     * (KYC, subscriptions) routed here by misconfiguration must be ignored,
     * never accidentally state-transitioned.
     *
     * @param  array<string, mixed>  $session
     */
    private function onCheckoutCompleted(array $session, string $eventId): void
    {
        if (! $this->isOurFlow($session)) {
            Log::info('cards.webhook.checkout_completed.not_our_flow', [
                'event_id' => $eventId,
            ]);

            return;
        }

        $this->service->applyCheckoutCompleted($session, $eventId);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function onCheckoutExpired(array $session, string $eventId): void
    {
        if (! $this->isOurFlow($session)) {
            return;
        }
        $this->service->applyCheckoutExpired($session, $eventId);
    }

    /**
     * charge.refunded carries the payment_intent id; we route by PI rather
     * than metadata.flow because the charge object may not have metadata
     * copied from the parent Checkout session. The service's UPDATE is
     * gated on the PI matching a card_waitlist_deposits row.
     *
     * @param  array<string, mixed>  $charge
     */
    private function onChargeRefunded(array $charge, string $eventId): void
    {
        $this->service->applyChargeRefunded($charge, $eventId);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function isOurFlow(array $object): bool
    {
        /** @var array<string, mixed> $metadata */
        $metadata = (array) ($object['metadata'] ?? []);
        $flow = (string) ($metadata['flow'] ?? '');

        return $flow === WaitlistDepositService::CHECKOUT_FLOW;
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
            Log::warning('cards.webhook.signature_invalid', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
