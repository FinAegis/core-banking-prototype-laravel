<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\TrustCert\Models\VerificationPayment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handle Stripe webhook events for KYC verification payments.
 *
 * Setup in Stripe Dashboard:
 *   Endpoint URL: https://zelta.app/api/webhooks/stripe/kyc
 *   Events:
 *     - checkout.session.completed              → mark application paid
 *     - checkout.session.expired                → revert to payment_required
 *     - checkout.session.async_payment_failed   → revert to payment_required
 *     - charge.refunded                         → mark application refunded
 */
class StripeKycWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('StripeKyc: Webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = (string) $request->input('type', '');

        Log::info('StripeKyc: Webhook received', [
            'type' => $eventType,
        ]);

        match ($eventType) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($request->all()),
            'checkout.session.expired',
            'checkout.session.async_payment_failed' => $this->handleCheckoutFailed($request->all(), $eventType),
            'charge.refunded'                       => $this->handleChargeRefunded($request->all()),
            default                                 => Log::debug('StripeKyc: Unhandled event type', ['type' => $eventType]),
        };

        return response()->json(['received' => true]);
    }

    /**
     * Handle a successful Stripe Checkout session.
     *
     * @param array<string, mixed> $payload
     */
    private function handleCheckoutCompleted(array $payload): void
    {
        $session = $payload['data']['object'] ?? [];

        $applicationId = $session['metadata']['application_id'] ?? '';
        $userId = $session['metadata']['user_id'] ?? '';
        $level = $session['metadata']['level'] ?? '';
        $sessionId = $session['id'] ?? '';
        $amountTotal = $session['amount_total'] ?? 0;

        if ($applicationId === '' || $userId === '') {
            Log::warning('StripeKyc: Missing metadata in checkout session', [
                'session_id' => $sessionId,
            ]);

            return;
        }

        $userIdInt = (int) $userId;
        $amount = bcdiv((string) $amountTotal, '100', 2);

        // Check if already processed (idempotency)
        $existing = VerificationPayment::where('application_id', (string) $applicationId)
            ->where('status', 'completed')
            ->first();

        if ($existing) {
            Log::info('StripeKyc: Payment already recorded for application', [
                'application_id' => $applicationId,
                'session_id'     => $sessionId,
            ]);

            return;
        }

        DB::transaction(function () use ($userIdInt, $applicationId, $amount, $sessionId): void {
            VerificationPayment::create([
                'user_id'           => $userIdInt,
                'application_id'    => (string) $applicationId,
                'method'            => 'card',
                'amount'            => $amount,
                'currency'          => 'USD',
                'status'            => 'completed',
                'stripe_session_id' => (string) $sessionId,
            ]);

            $this->markApplicationPaid($userIdInt, (string) $applicationId, (string) $sessionId, $amount);
        });

        Log::info('StripeKyc: Application marked as paid', [
            'application_id' => $applicationId,
            'user_id'        => $userIdInt,
            'amount'         => $amount,
            'session_id'     => $sessionId,
        ]);
    }

    /**
     * Handle a failed/expired Stripe Checkout session.
     *
     * Reverts the application back to `payment_required` so mobile can
     * re-attempt payment. Idempotent: skips if no completed payment exists
     * for the session, or if the application is already in a non-paid state.
     *
     * @param array<string, mixed> $payload
     */
    private function handleCheckoutFailed(array $payload, string $eventType): void
    {
        $session = $payload['data']['object'] ?? [];
        $applicationId = (string) ($session['metadata']['application_id'] ?? '');
        $userId = (int) ($session['metadata']['user_id'] ?? 0);
        $sessionId = (string) ($session['id'] ?? '');

        if ($applicationId === '' || $userId === 0) {
            Log::warning('StripeKyc: Missing metadata in failed/expired session', [
                'event_type' => $eventType,
                'session_id' => $sessionId,
            ]);

            return;
        }

        // If we already recorded this session as completed, it succeeded
        // before the expiry/async-failed event. Don't unwind a real payment.
        $alreadyCompleted = VerificationPayment::where('stripe_session_id', $sessionId)
            ->where('status', 'completed')
            ->exists();

        if ($alreadyCompleted) {
            Log::info('StripeKyc: Ignoring failure event — payment already completed', [
                'event_type' => $eventType,
                'session_id' => $sessionId,
            ]);

            return;
        }

        $this->updateApplicationStatus(
            userId: $userId,
            applicationId: $applicationId,
            newStatus: 'payment_required',
            metaUpdates: [
                'last_payment_failure_reason' => $eventType,
                'last_payment_session_id'     => $sessionId,
                'last_payment_failed_at'      => now()->toIso8601String(),
            ],
        );

        Log::info('StripeKyc: Application reverted to payment_required after failure', [
            'event_type'     => $eventType,
            'application_id' => $applicationId,
            'session_id'     => $sessionId,
        ]);
    }

    /**
     * Handle a refunded Stripe charge.
     *
     * @param array<string, mixed> $payload
     */
    private function handleChargeRefunded(array $payload): void
    {
        $charge = $payload['data']['object'] ?? [];
        $sessionId = (string) ($charge['metadata']['checkout_session_id'] ?? '');
        $paymentIntentId = (string) ($charge['payment_intent'] ?? '');

        // Find the original payment row. Stripe's charge.refunded event
        // doesn't always carry the checkout session id, so fall back to
        // payment_intent or charge id matching when possible.
        $payment = VerificationPayment::query()
            ->when($sessionId !== '', fn ($q) => $q->where('stripe_session_id', $sessionId))
            ->where('status', 'completed')
            ->first();

        if ($payment === null && $paymentIntentId !== '') {
            // Best-effort: log unmatched refund so finance can reconcile.
            Log::warning('StripeKyc: charge.refunded without a matched local payment', [
                'session_id'        => $sessionId,
                'payment_intent_id' => $paymentIntentId,
            ]);

            return;
        }

        if ($payment === null) {
            Log::warning('StripeKyc: charge.refunded with no metadata to match', [
                'charge_id' => $charge['id'] ?? null,
            ]);

            return;
        }

        // Idempotent: skip if already refunded.
        if ($payment->status === 'refunded') {
            return;
        }

        DB::transaction(function () use ($payment, $charge): void {
            $payment->update([
                'status' => 'refunded',
            ]);

            $this->updateApplicationStatus(
                userId: (int) $payment->user_id,
                applicationId: (string) $payment->application_id,
                newStatus: 'refunded',
                metaUpdates: [
                    'refunded_at'     => now()->toIso8601String(),
                    'refund_amount'   => bcdiv((string) ($charge['amount_refunded'] ?? 0), '100', 2),
                    'refund_currency' => strtoupper((string) ($charge['currency'] ?? 'usd')),
                ],
            );
        });

        Log::info('StripeKyc: Application marked as refunded', [
            'application_id' => $payment->application_id,
            'session_id'     => $payment->stripe_session_id,
        ]);
    }

    /**
     * Update the cached application status with optional metadata.
     *
     * @param array<string, mixed> $metaUpdates
     */
    private function updateApplicationStatus(int $userId, string $applicationId, string $newStatus, array $metaUpdates = []): void
    {
        /** @var array<string, mixed>|null $application */
        $application = Cache::get("trustcert_application:{$userId}");

        if (! is_array($application) || ($application['id'] ?? '') !== $applicationId) {
            Log::warning('StripeKyc: Application not found in cache for status update', [
                'user_id'        => $userId,
                'application_id' => $applicationId,
                'new_status'     => $newStatus,
            ]);

            return;
        }

        $application['status'] = $newStatus;
        $application['updated_at'] = now()->toIso8601String();
        foreach ($metaUpdates as $k => $v) {
            $application[$k] = $v;
        }

        Cache::put("trustcert_application:{$userId}", $application, now()->addDays(30));
    }

    /**
     * Mark the cached application as paid.
     */
    private function markApplicationPaid(int $userId, string $applicationId, string $sessionId, string $amount): void
    {
        /** @var array<string, mixed>|null $application */
        $application = Cache::get("trustcert_application:{$userId}");

        if (! is_array($application) || ($application['id'] ?? '') !== $applicationId) {
            Log::warning('StripeKyc: Application not found in cache for payment update', [
                'user_id'        => $userId,
                'application_id' => $applicationId,
            ]);

            return;
        }

        $application['status'] = 'paid';
        $application['paid_at'] = now()->toIso8601String();
        $application['payment_method'] = 'card';
        $application['payment_receipt_id'] = $sessionId;
        $application['payment_amount'] = $amount;
        $application['updated_at'] = now()->toIso8601String();

        Cache::put("trustcert_application:{$userId}", $application, now()->addDays(30));
    }

    /**
     * Verify the Stripe webhook signature.
     *
     * Uses the STRIPE_KYC_WEBHOOK_SECRET env var. Falls back to
     * STRIPE_WEBHOOK_SECRET if the KYC-specific one is not set.
     */
    private function verifySignature(Request $request): bool
    {
        $secret = (string) config('services.stripe.kyc_webhook_secret', '');

        if ($secret === '') {
            $secret = (string) config('services.stripe.webhook_secret', '');
        }

        if ($secret === '') {
            if (app()->environment('production')) {
                Log::error('StripeKyc: No webhook secret configured in production');

                return false;
            }

            // Allow unsigned webhooks in non-production
            return true;
        }

        $signatureHeader = $request->header('Stripe-Signature', '');

        if (! is_string($signatureHeader) || $signatureHeader === '') {
            return false;
        }

        // Parse Stripe signature header: t=timestamp,v1=signature
        $elements = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $elements[$kv[0]] = $kv[1];
            }
        }

        $timestamp = $elements['t'] ?? '';
        $signature = $elements['v1'] ?? '';

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            Log::warning('StripeKycWebhook: timestamp too old');

            return false;
        }

        $payload = $timestamp . '.' . $request->getContent();
        $computed = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computed, $signature);
    }
}
