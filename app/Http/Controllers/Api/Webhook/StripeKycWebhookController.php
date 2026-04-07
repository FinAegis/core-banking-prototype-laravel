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
 * Listens for checkout.session.completed events to mark
 * trust certificate applications as paid after card payment.
 *
 * Setup in Stripe Dashboard:
 *   Endpoint URL: https://zelta.app/api/webhooks/stripe/kyc
 *   Events: checkout.session.completed
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

        if ($eventType === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($request->all());
        } else {
            Log::debug('StripeKyc: Unhandled event type', ['type' => $eventType]);
        }

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
