<?php

declare(strict_types=1);

namespace App\Domain\X402\Services;

use App\Domain\X402\Models\WebSocketSubscription;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates paid WebSocket channel subscriptions.
 *
 * Manages subscription lifecycle: creation on payment, active checks,
 * and expiry handling for premium real-time data feeds.
 */
class WebSocketPaymentService
{
    /**
     * Check if a channel requires payment.
     */
    public function isPremiumChannel(string $channel): bool
    {
        /** @var array<string, array{price: string, protocol: string, duration_seconds: int}> $premiumChannels */
        $premiumChannels = (array) config('websocket.premium_channels', []);

        foreach (array_keys($premiumChannels) as $pattern) {
            if ($this->channelMatchesPattern($channel, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the pricing config for a premium channel.
     *
     * @return array{price: string, protocol: string, duration_seconds: int}|null
     */
    public function getChannelPricing(string $channel): ?array
    {
        /** @var array<string, array{price: string, protocol: string, duration_seconds: int}> $premiumChannels */
        $premiumChannels = (array) config('websocket.premium_channels', []);

        foreach ($premiumChannels as $pattern => $pricing) {
            if ($this->channelMatchesPattern($channel, $pattern)) {
                return $pricing;
            }
        }

        return null;
    }

    /**
     * Check if a user or agent has an active subscription to a channel.
     */
    public function isSubscriptionActive(?int $userId, ?string $agentId, string $channel): bool
    {
        $query = WebSocketSubscription::active()->forChannel($channel);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } elseif ($agentId !== null) {
            $query->where('agent_id', $agentId);
        } else {
            return false;
        }

        return $query->exists();
    }

    /**
     * Create a subscription after successful payment.
     *
     * @param array{price: string, protocol: string, duration_seconds: int} $pricing
     */
    public function createSubscription(
        string $channel,
        array $pricing,
        ?int $userId = null,
        ?string $agentId = null,
        ?string $paymentId = null,
        ?string $network = null,
    ): WebSocketSubscription {
        $subscription = WebSocketSubscription::create([
            'user_id'    => $userId,
            'agent_id'   => $agentId,
            'channel'    => $channel,
            'protocol'   => $pricing['protocol'],
            'payment_id' => $paymentId,
            'amount'     => $pricing['price'],
            'network'    => $network,
            'expires_at' => now()->addSeconds($pricing['duration_seconds']),
        ]);

        Log::info('ws: Created paid channel subscription', [
            'channel'    => $channel,
            'user_id'    => $userId,
            'agent_id'   => $agentId,
            'protocol'   => $pricing['protocol'],
            'expires_at' => $subscription->expires_at->toIso8601String(),
        ]);

        return $subscription;
    }

    /**
     * Get active subscriptions for a user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, WebSocketSubscription>
     */
    public function getActiveSubscriptions(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return WebSocketSubscription::active()
            ->forUser($userId)
            ->orderBy('expires_at', 'asc')
            ->get();
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        $subscription = WebSocketSubscription::find($subscriptionId);

        if ($subscription === null) {
            return false;
        }

        // Expire immediately
        $subscription->update(['expires_at' => now()]);

        Log::info('ws: Cancelled channel subscription', [
            'subscription_id' => $subscriptionId,
            'channel'         => $subscription->channel,
        ]);

        return true;
    }

    /**
     * Check if a channel name matches a wildcard pattern.
     *
     * Supports '*' as a wildcard for a single segment.
     */
    private function channelMatchesPattern(string $channel, string $pattern): bool
    {
        $regex = '/^' . str_replace(['\\*', '\\.'], ['[^.]+', '\\.'], preg_quote($pattern, '/')) . '$/';

        return (bool) preg_match($regex, $channel);
    }
}
