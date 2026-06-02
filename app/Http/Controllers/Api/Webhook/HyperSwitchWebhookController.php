<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\Account\Services\AccountCreditService;
use App\Domain\Payment\Aggregates\PaymentDepositAggregate;
use App\Domain\Payment\Models\HyperSwitchDepositIntent;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handle HyperSwitch payment lifecycle webhooks.
 *
 * `payment_succeeded` completes the matching deposit: it credits the account
 * (amount taken from the stored intent, never the webhook payload) and records
 * `DepositCompleted` on the deposit aggregate. `payment_failed` fails the
 * aggregate. Both are idempotent via `processed_webhook_events`.
 *
 * Setup in HyperSwitch dashboard or via API:
 *   webhook_url: https://zelta.app/api/webhooks/hyperswitch
 */
class HyperSwitchWebhookController extends Controller
{
    public function __construct(
        private readonly AccountCreditService $creditService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('HyperSwitch: Webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = (string) $request->input('event_type', '');
        $paymentId = (string) $request->input('content.object.payment_id', '');

        Log::info('HyperSwitch: Webhook received', [
            'event_type' => $eventType,
            'payment_id' => $paymentId,
        ]);

        match ($eventType) {
            'payment_succeeded'  => $this->handlePaymentSucceeded($request->all()),
            'payment_failed'     => $this->handlePaymentFailed($request->all()),
            'payment_processing' => $this->handlePaymentProcessing($request->all()),
            'refund_succeeded'   => $this->handleRefundSucceeded($request->all()),
            'refund_failed'      => $this->handleRefundFailed($request->all()),
            'dispute_opened'     => $this->handleDisputeOpened($request->all()),
            default              => Log::debug('HyperSwitch: Unhandled event type', ['event_type' => $eventType]),
        };

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handlePaymentSucceeded(array $payload): void
    {
        $object = is_array($payload['content']['object'] ?? null) ? $payload['content']['object'] : [];
        $paymentId = (string) ($object['payment_id'] ?? '');
        $eventId = (string) ($payload['event_id'] ?? '');

        if ($paymentId === '') {
            Log::warning('HyperSwitch: payment_succeeded with no payment_id');

            return;
        }
        // Fall back to a payment-scoped dedupe key when the provider omits event_id.
        if ($eventId === '') {
            $eventId = 'hs_succeeded_' . $paymentId;
        }

        // Idempotency + intent claim — DEFAULT connection only.
        $claim = DB::transaction(function () use ($paymentId, $eventId): ?array {
            $event = ProcessedWebhookEvent::firstOrCreate(
                ['provider' => 'hyperswitch', 'event_id' => $eventId],
                ['event_type' => 'payment_succeeded', 'processed_at' => now()],
            );
            if (! $event->wasRecentlyCreated) {
                return null; // replay
            }

            $intent = HyperSwitchDepositIntent::where('hyperswitch_payment_id', $paymentId)
                ->lockForUpdate()
                ->first();
            if ($intent === null) {
                Log::warning('HyperSwitch: payment_succeeded with no matching deposit intent', [
                    'payment_id' => $paymentId,
                ]);

                return null;
            }
            if ($intent->status !== HyperSwitchDepositIntent::STATUS_PENDING) {
                return null; // already terminal
            }

            $intent->update(['status' => HyperSwitchDepositIntent::STATUS_COMPLETED]);

            return [
                'deposit_uuid' => (string) $intent->deposit_uuid,
                'account_uuid' => (string) $intent->account_uuid,
                'amount_cents' => (int) $intent->amount_cents,
                'currency'     => (string) $intent->currency,
                'payment_id'   => $paymentId,
            ];
        });

        if ($claim === null) {
            return;
        }

        // Credit + audit run on the TENANT connection, AFTER the default-conn
        // claim above committed — never one transaction spanning both
        // connections (that self-deadlocks; CLAUDE.md). They are kept as
        // separate, sequential operations (NOT co-wrapped in one transaction):
        // the aggregate persist + its projectors don't compose with the credit's
        // own row-lock transaction under the real multi-session topology, and
        // co-wrapping rolls the credit back on a persist hiccup. Credit-first is
        // deliberate — the user receives funds; a persist failure leaves a
        // reconcilable audit gap (logged), never money lost. Matches the shipped
        // Bridge ramp webhook.
        try {
            $this->creditService->credit($claim['account_uuid'], $claim['amount_cents'], $claim['currency']);
            PaymentDepositAggregate::retrieve($claim['deposit_uuid'])
                ->completeDeposit('hs_' . $claim['payment_id'])
                ->persist();
        } catch (Throwable $e) {
            // The dedupe row is already committed, so retries won't re-run this;
            // surface for operator reconciliation instead of failing the webhook.
            Log::error('HyperSwitch: deposit completion failed after claim', [
                'payment_id'   => $claim['payment_id'],
                'deposit_uuid' => $claim['deposit_uuid'],
                'exception'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handlePaymentFailed(array $payload): void
    {
        $object = is_array($payload['content']['object'] ?? null) ? $payload['content']['object'] : [];
        $paymentId = (string) ($object['payment_id'] ?? '');
        $error = (string) ($object['error_message'] ?? 'payment_failed');
        $eventId = (string) ($payload['event_id'] ?? '');

        if ($paymentId === '') {
            Log::warning('HyperSwitch: payment_failed with no payment_id');

            return;
        }
        if ($eventId === '') {
            $eventId = 'hs_failed_' . $paymentId;
        }

        $claim = DB::transaction(function () use ($paymentId, $eventId): ?array {
            $event = ProcessedWebhookEvent::firstOrCreate(
                ['provider' => 'hyperswitch', 'event_id' => $eventId],
                ['event_type' => 'payment_failed', 'processed_at' => now()],
            );
            if (! $event->wasRecentlyCreated) {
                return null;
            }

            $intent = HyperSwitchDepositIntent::where('hyperswitch_payment_id', $paymentId)
                ->lockForUpdate()
                ->first();
            if ($intent === null || $intent->status !== HyperSwitchDepositIntent::STATUS_PENDING) {
                return null;
            }

            $intent->update(['status' => HyperSwitchDepositIntent::STATUS_FAILED]);

            return ['deposit_uuid' => (string) $intent->deposit_uuid];
        });

        if ($claim === null) {
            return;
        }

        try {
            PaymentDepositAggregate::retrieve($claim['deposit_uuid'])
                ->failDeposit($error)
                ->persist();
        } catch (Throwable $e) {
            Log::error('HyperSwitch: deposit fail-marking errored', [
                'payment_id' => $paymentId,
                'exception'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handlePaymentProcessing(array $payload): void
    {
        Log::info('HyperSwitch: Payment processing', [
            'payment_id' => $payload['content']['object']['payment_id'] ?? '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleRefundSucceeded(array $payload): void
    {
        Log::info('HyperSwitch: Refund succeeded', [
            'refund_id'  => $payload['content']['object']['refund_id'] ?? '',
            'payment_id' => $payload['content']['object']['payment_id'] ?? '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleRefundFailed(array $payload): void
    {
        Log::warning('HyperSwitch: Refund failed', [
            'refund_id' => $payload['content']['object']['refund_id'] ?? '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleDisputeOpened(array $payload): void
    {
        Log::warning('HyperSwitch: Dispute opened', [
            'payment_id' => $payload['content']['object']['payment_id'] ?? '',
        ]);
    }

    /**
     * Verify the webhook signature.
     *
     * HyperSwitch signs webhooks using HMAC-SHA512 over the raw body.
     */
    private function verifySignature(Request $request): bool
    {
        $secret = (string) config('hyperswitch.webhook_secret', '');

        if ($secret === '') {
            if (app()->environment('production')) {
                Log::error('HyperSwitch: HYPERSWITCH_WEBHOOK_SECRET not set in production');

                return false;
            }

            return true;
        }

        $signature = $request->header('x-webhook-signature-512', '');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $computed = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }
}
