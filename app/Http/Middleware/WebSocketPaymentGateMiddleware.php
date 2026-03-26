<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\X402\Services\WebSocketPaymentService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payment gate for premium WebSocket channel subscriptions.
 *
 * Applied to the broadcasting auth endpoint. When a client subscribes to
 * a premium channel, checks for active subscription or payment header.
 * Returns 402 Payment Required with pricing info if no valid subscription.
 */
class WebSocketPaymentGateMiddleware
{
    public function __construct(
        private readonly WebSocketPaymentService $paymentService,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $channelName = $request->input('channel_name', '');

        if (! is_string($channelName) || $channelName === '') {
            return $next($request);
        }

        // Strip Pusher private-/presence- prefix for pattern matching
        $cleanChannel = preg_replace('/^(private-|presence-)/', '', $channelName) ?? $channelName;

        if (! $this->paymentService->isPremiumChannel($cleanChannel)) {
            return $next($request);
        }

        $userId = $request->user()?->id;
        $agentId = $request->header('X-Agent-ID');

        // Check active subscription
        if ($this->paymentService->isSubscriptionActive($userId, $agentId, $cleanChannel)) {
            return $next($request);
        }

        // No active subscription — check for payment headers
        $pricing = $this->paymentService->getChannelPricing($cleanChannel);

        // Check for x402 payment header
        $paymentSignature = $request->header('PAYMENT-SIGNATURE');
        if ($paymentSignature !== null) {
            return $this->handleX402Payment($request, $next, $cleanChannel, $pricing, $paymentSignature);
        }

        // Check for MPP payment header
        $authorization = $request->header('Authorization');
        if ($authorization !== null && str_starts_with($authorization, 'Payment ')) {
            return $this->handleMppPayment($request, $next, $cleanChannel, $pricing, $authorization);
        }

        // No payment header — return 402 with pricing
        return response()->json([
            'error'   => 'PAYMENT_REQUIRED',
            'message' => 'This channel requires a paid subscription.',
            'channel' => $cleanChannel,
            'pricing' => $pricing,
        ], 402);
    }

    /**
     * Handle x402 protocol payment via PAYMENT-SIGNATURE header.
     *
     * @param Closure(Request): Response $next
     * @param array{price: string, protocol: string, duration_seconds: int}|null $pricing
     */
    private function handleX402Payment(
        Request $request,
        Closure $next,
        string $channel,
        ?array $pricing,
        string $paymentSignature,
    ): Response {
        $decoded = base64_decode($paymentSignature, true);
        if ($decoded === false) {
            return $this->paymentErrorResponse($channel, $pricing, 'Invalid PAYMENT-SIGNATURE: base64 decode failed.');
        }

        /** @var array{payment_id?: string}|null $payload */
        $payload = json_decode($decoded, true);
        if (! is_array($payload) || ! isset($payload['payment_id'])) {
            return $this->paymentErrorResponse($channel, $pricing, 'Invalid PAYMENT-SIGNATURE: missing payment_id.');
        }

        $paymentId = (string) $payload['payment_id'];
        $userId = $request->user()?->id;
        $agentId = $request->header('X-Agent-ID');

        Log::info('ws: x402 payment attempt', [
            'channel'    => $channel,
            'payment_id' => $paymentId,
            'user_id'    => $userId,
            'agent_id'   => $agentId,
        ]);

        if ($pricing === null) {
            return $this->paymentErrorResponse($channel, $pricing, 'Channel pricing not found.');
        }

        // SECURITY: In production, verify the payment via facilitator before granting access.
        // The payment_id must be validated against X402PaymentVerificationService or
        // on-chain settlement status. Without verification, any crafted header grants access.
        if (app()->isProduction()) {
            return $this->paymentErrorResponse($channel, $pricing, 'WebSocket payment verification not yet enabled for production.');
        }

        $this->paymentService->createSubscription(
            channel: $channel,
            pricing: array_merge($pricing, ['protocol' => 'x402']),
            userId: $userId,
            agentId: $agentId,
            paymentId: $paymentId,
        );

        return $next($request);
    }

    /**
     * Handle MPP protocol payment via Authorization: Payment header.
     *
     * @param Closure(Request): Response $next
     * @param array{price: string, protocol: string, duration_seconds: int}|null $pricing
     */
    private function handleMppPayment(
        Request $request,
        Closure $next,
        string $channel,
        ?array $pricing,
        string $authorization,
    ): Response {
        $credential = substr($authorization, strlen('Payment '));
        $decoded = base64_decode($credential, true);
        if ($decoded === false) {
            return $this->paymentErrorResponse($channel, $pricing, 'Invalid Authorization: base64 decode failed.');
        }

        /** @var array{challenge_id?: string}|null $payload */
        $payload = json_decode($decoded, true);
        if (! is_array($payload) || ! isset($payload['challenge_id'])) {
            return $this->paymentErrorResponse($channel, $pricing, 'Invalid Authorization: missing challenge_id.');
        }

        $challengeId = (string) $payload['challenge_id'];
        $userId = $request->user()?->id;
        $agentId = $request->header('X-Agent-ID');

        Log::info('ws: MPP payment attempt', [
            'channel'      => $channel,
            'challenge_id' => $challengeId,
            'user_id'      => $userId,
            'agent_id'     => $agentId,
        ]);

        if ($pricing === null) {
            return $this->paymentErrorResponse($channel, $pricing, 'Channel pricing not found.');
        }

        // SECURITY: In production, verify the MPP credential via MppVerificationService
        // before granting access. Without verification, any crafted header grants access.
        if (app()->isProduction()) {
            return $this->paymentErrorResponse($channel, $pricing, 'WebSocket payment verification not yet enabled for production.');
        }

        $this->paymentService->createSubscription(
            channel: $channel,
            pricing: array_merge($pricing, ['protocol' => 'mpp']),
            userId: $userId,
            agentId: $agentId,
            paymentId: $challengeId,
        );

        return $next($request);
    }

    /**
     * Return a 402 error response with optional payment error detail.
     *
     * @param array{price: string, protocol: string, duration_seconds: int}|null $pricing
     */
    private function paymentErrorResponse(string $channel, ?array $pricing, string $detail): Response
    {
        return response()->json([
            'error'   => 'PAYMENT_REQUIRED',
            'message' => $detail,
            'channel' => $channel,
            'pricing' => $pricing,
        ], 402);
    }
}
