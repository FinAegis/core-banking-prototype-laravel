<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\X402\Services\WebSocketPaymentService;
use Closure;
use Illuminate\Http\Request;
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

        // No active subscription — return 402 with pricing
        $pricing = $this->paymentService->getChannelPricing($cleanChannel);

        return response()->json([
            'error'   => 'PAYMENT_REQUIRED',
            'message' => 'This channel requires a paid subscription.',
            'channel' => $cleanChannel,
            'pricing' => $pricing,
        ], 402);
    }
}
