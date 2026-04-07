<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\Ramp\Services\StripeBridgeService;
use App\Http\Controllers\Controller;
use App\Models\RampSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handle Stripe Bridge (Crypto Onramp) webhooks.
 *
 * Stripe sends events for onramp session lifecycle:
 * - crypto_onramp.session.updated
 * - crypto_onramp.session.completed
 *
 * Webhook URL: POST /api/webhooks/stripe/bridge
 */
class StripeBridgeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeBridgeService $service,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('Stripe Bridge: Webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = (string) $request->input('type', '');
        $sessionData = (array) $request->input('data.object', []);

        Log::info('Stripe Bridge: Webhook received', [
            'event_type' => $eventType,
            'session_id' => $sessionData['id'] ?? 'unknown',
        ]);

        match ($eventType) {
            'crypto_onramp.session.updated'   => $this->handleSessionUpdated($sessionData),
            'crypto_onramp.session.completed' => $this->handleSessionCompleted($sessionData),
            default                           => Log::debug('Stripe Bridge: Unhandled event type', ['event_type' => $eventType]),
        };

        return response()->json(['received' => true]);
    }

    /**
     * Handle session status updates from Stripe.
     *
     * @param array<string, mixed> $sessionData
     */
    private function handleSessionUpdated(array $sessionData): void
    {
        $stripeSessionId = (string) ($sessionData['id'] ?? '');
        if ($stripeSessionId === '') {
            Log::warning('Stripe Bridge: Webhook missing session ID');

            return;
        }

        $session = $this->findSession($stripeSessionId);
        if (! $session) {
            return;
        }

        // Idempotency: don't overwrite terminal states
        if (in_array($session->status, [RampSession::STATUS_COMPLETED, RampSession::STATUS_FAILED, RampSession::STATUS_EXPIRED], true)) {
            Log::info('Stripe Bridge: Skipping update for terminal session', [
                'session_id' => $session->id,
                'status'     => $session->status,
            ]);

            return;
        }

        $stripeStatus = (string) ($sessionData['status'] ?? '');
        $mappedStatus = $this->service->mapStripeStatus($stripeStatus);
        $rawAmount = isset($sessionData['destination_amount']) ? (string) $sessionData['destination_amount'] : null;
        $cryptoAmount = $rawAmount !== null && is_numeric($rawAmount)
            ? bcadd($rawAmount, '0', 8)
            : null;

        $updateData = [
            'status'   => $mappedStatus,
            'metadata' => array_merge($session->metadata ?? [], [
                'stripe_status'       => $stripeStatus,
                'stripe_status_label' => $this->service->mapStripeStatusLabel($stripeStatus),
                'last_webhook_at'     => now()->toIso8601String(),
            ]),
        ];

        if ($cryptoAmount !== null) {
            $updateData['crypto_amount'] = $cryptoAmount;
        }

        $session->update($updateData);

        Log::info('Stripe Bridge: Session updated via webhook', [
            'session_id'    => $session->id,
            'stripe_status' => $stripeStatus,
            'mapped_status' => $mappedStatus,
        ]);
    }

    /**
     * Handle completed session from Stripe.
     *
     * @param array<string, mixed> $sessionData
     */
    private function handleSessionCompleted(array $sessionData): void
    {
        $stripeSessionId = (string) ($sessionData['id'] ?? '');
        if ($stripeSessionId === '') {
            Log::warning('Stripe Bridge: Completed webhook missing session ID');

            return;
        }

        $session = $this->findSession($stripeSessionId);
        if (! $session) {
            return;
        }

        // Idempotency: already completed
        if ($session->status === RampSession::STATUS_COMPLETED) {
            Log::info('Stripe Bridge: Session already completed', ['session_id' => $session->id]);

            return;
        }

        $rawAmount = isset($sessionData['destination_amount']) ? (string) $sessionData['destination_amount'] : null;
        $cryptoAmount = $rawAmount !== null && is_numeric($rawAmount)
            ? bcadd($rawAmount, '0', 8)
            : null;

        $updateData = [
            'status'   => RampSession::STATUS_COMPLETED,
            'metadata' => array_merge($session->metadata ?? [], [
                'stripe_status'       => 'fulfilled',
                'stripe_status_label' => 'Completed',
                'completed_at'        => now()->toIso8601String(),
                'last_webhook_at'     => now()->toIso8601String(),
                'transaction_hash'    => $sessionData['transaction_hash'] ?? null,
            ]),
        ];

        if ($cryptoAmount !== null) {
            $updateData['crypto_amount'] = $cryptoAmount;
        }

        $session->update($updateData);

        Log::info('Stripe Bridge: Session completed', [
            'session_id'    => $session->id,
            'crypto_amount' => $cryptoAmount,
        ]);
    }

    /**
     * Find a ramp session by Stripe session ID.
     */
    private function findSession(string $stripeSessionId): ?RampSession
    {
        $session = RampSession::where('stripe_session_id', $stripeSessionId)
            ->orWhere('provider_session_id', $stripeSessionId)
            ->first();

        if (! $session) {
            Log::warning('Stripe Bridge: Session not found for webhook', [
                'stripe_session_id' => $stripeSessionId,
            ]);

            return null;
        }

        if ($session->provider !== 'stripe_bridge') {
            Log::warning('Stripe Bridge: Provider mismatch for session', [
                'session_id' => $session->id,
                'expected'   => 'stripe_bridge',
                'actual'     => $session->provider,
            ]);

            return null;
        }

        return $session;
    }

    /**
     * Verify the Stripe webhook signature.
     *
     * Stripe signs webhooks using HMAC-SHA256 via the Stripe-Signature header.
     */
    private function verifySignature(Request $request): bool
    {
        $secret = (string) config('services.stripe.bridge_webhook_secret', '');

        if ($secret === '') {
            if (app()->environment('production')) {
                Log::error('Stripe Bridge: STRIPE_BRIDGE_WEBHOOK_SECRET not set in production');

                return false;
            }

            // Allow in non-production for development/testing
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
        $v1Signature = $elements['v1'] ?? '';

        if ($timestamp === '' || $v1Signature === '') {
            return false;
        }

        // Verify timestamp is within tolerance (5 minutes)
        $tolerance = 300;
        if (abs(time() - (int) $timestamp) > $tolerance) {
            Log::warning('Stripe Bridge: Webhook timestamp outside tolerance', [
                'timestamp' => $timestamp,
            ]);

            return false;
        }

        $signedPayload = $timestamp . '.' . $request->getContent();
        $computed = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($computed, $v1Signature);
    }
}
