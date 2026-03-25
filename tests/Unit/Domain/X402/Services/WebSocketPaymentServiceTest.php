<?php

declare(strict_types=1);

use App\Domain\X402\Models\WebSocketSubscription;
use App\Domain\X402\Services\WebSocketPaymentService;
use App\Models\User;

uses(Tests\TestCase::class);

describe('WebSocketPaymentService', function (): void {
    beforeEach(function (): void {
        config(['websocket.premium_channels' => [
            'tenant.*.exchange.orderbook' => [
                'price'            => '1000',
                'protocol'         => 'x402',
                'duration_seconds' => 3600,
            ],
            'tenant.*.exchange.trades' => [
                'price'            => '5000',
                'protocol'         => 'mpp',
                'duration_seconds' => 7200,
            ],
        ]]);
    });

    it('identifies premium channels', function (): void {
        $service = new WebSocketPaymentService();

        expect($service->isPremiumChannel('tenant.1.exchange.orderbook'))->toBeTrue();
        expect($service->isPremiumChannel('tenant.42.exchange.trades'))->toBeTrue();
        expect($service->isPremiumChannel('tenant.1.accounts'))->toBeFalse();
        expect($service->isPremiumChannel('user.1'))->toBeFalse();
    });

    it('returns pricing for premium channels', function (): void {
        $service = new WebSocketPaymentService();

        $pricing = $service->getChannelPricing('tenant.1.exchange.orderbook');

        expect($pricing)->not->toBeNull();
        assert(is_array($pricing));
        expect($pricing['price'])->toBe('1000');
        expect($pricing['protocol'])->toBe('x402');
        expect($pricing['duration_seconds'])->toBe(3600);
    });

    it('returns null pricing for non-premium channels', function (): void {
        $service = new WebSocketPaymentService();

        expect($service->getChannelPricing('tenant.1.accounts'))->toBeNull();
    });

    it('creates a subscription with correct expiry', function (): void {
        $service = new WebSocketPaymentService();
        $user = User::factory()->create();

        $sub = $service->createSubscription(
            channel: 'tenant.1.exchange.orderbook',
            pricing: ['price' => '1000', 'protocol' => 'x402', 'duration_seconds' => 3600],
            userId: $user->id,
        );

        expect($sub)->toBeInstanceOf(WebSocketSubscription::class);
        expect($sub->channel)->toBe('tenant.1.exchange.orderbook');
        expect($sub->protocol)->toBe('x402');
        expect($sub->isActive())->toBeTrue();
        expect($sub->expires_at->diffInSeconds(now(), true))->toBeLessThan(3610);
    });

    it('detects active subscriptions', function (): void {
        $service = new WebSocketPaymentService();
        $user = User::factory()->create();

        expect($service->isSubscriptionActive($user->id, null, 'tenant.1.exchange.orderbook'))->toBeFalse();

        WebSocketSubscription::create([
            'user_id'    => $user->id,
            'channel'    => 'tenant.1.exchange.orderbook',
            'protocol'   => 'x402',
            'amount'     => '1000',
            'expires_at' => now()->addHour(),
        ]);

        expect($service->isSubscriptionActive($user->id, null, 'tenant.1.exchange.orderbook'))->toBeTrue();
    });

    it('does not count expired subscriptions as active', function (): void {
        $service = new WebSocketPaymentService();
        $user = User::factory()->create();

        WebSocketSubscription::create([
            'user_id'    => $user->id,
            'channel'    => 'tenant.1.exchange.orderbook',
            'protocol'   => 'x402',
            'amount'     => '1000',
            'expires_at' => now()->subMinute(),
        ]);

        expect($service->isSubscriptionActive($user->id, null, 'tenant.1.exchange.orderbook'))->toBeFalse();
    });

    it('cancels a subscription by expiring it immediately', function (): void {
        $service = new WebSocketPaymentService();
        $user = User::factory()->create();

        $sub = WebSocketSubscription::create([
            'user_id'    => $user->id,
            'channel'    => 'tenant.1.exchange.orderbook',
            'protocol'   => 'x402',
            'amount'     => '1000',
            'expires_at' => now()->addHour(),
        ]);

        expect($service->cancelSubscription($sub->id))->toBeTrue();

        $sub->refresh();
        expect($sub->isActive())->toBeFalse();
    });
});
