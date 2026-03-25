<?php

declare(strict_types=1);

use App\Domain\X402\Models\WebSocketSubscription;
use App\Http\Middleware\WebSocketPaymentGateMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

describe('WebSocketPaymentGateMiddleware', function (): void {
    beforeEach(function (): void {
        config(['websocket.premium_channels' => [
            'tenant.*.exchange.orderbook' => [
                'price'            => '1000',
                'protocol'         => 'x402',
                'duration_seconds' => 3600,
            ],
        ]]);
    });

    it('passes through for non-premium channels', function (): void {
        $middleware = app(WebSocketPaymentGateMiddleware::class);
        $request = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'private-tenant.1.accounts',
        ]);

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->getStatusCode())->toBe(200);
    });

    it('returns 402 for premium channel without subscription', function (): void {
        $user = User::factory()->create();

        $middleware = app(WebSocketPaymentGateMiddleware::class);
        $request = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'private-tenant.1.exchange.orderbook',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->getStatusCode())->toBe(402);
    });

    it('passes through when active subscription exists', function (): void {
        $user = User::factory()->create();

        WebSocketSubscription::create([
            'user_id'    => $user->id,
            'channel'    => 'tenant.1.exchange.orderbook',
            'protocol'   => 'x402',
            'amount'     => '1000',
            'expires_at' => now()->addHour(),
        ]);

        $middleware = app(WebSocketPaymentGateMiddleware::class);
        $request = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'private-tenant.1.exchange.orderbook',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->getStatusCode())->toBe(200);
    });

    it('returns 402 when subscription is expired', function (): void {
        $user = User::factory()->create();

        WebSocketSubscription::create([
            'user_id'    => $user->id,
            'channel'    => 'tenant.1.exchange.orderbook',
            'protocol'   => 'x402',
            'amount'     => '1000',
            'expires_at' => now()->subMinute(),
        ]);

        $middleware = app(WebSocketPaymentGateMiddleware::class);
        $request = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'private-tenant.1.exchange.orderbook',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => new Response('ok'));

        expect($response->getStatusCode())->toBe(402);
    });
});

describe('PaidChannelController', function (): void {
    it('lists active subscriptions for authenticated user', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        WebSocketSubscription::create([
            'user_id'    => $user->id,
            'channel'    => 'tenant.1.exchange.orderbook',
            'protocol'   => 'x402',
            'amount'     => '1000',
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->getJson('/api/websocket/subscriptions');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'channel', 'protocol', 'amount', 'expires_at'],
            ],
        ]);
        $response->assertJsonCount(1, 'data');
    });

    it('cancels a subscription', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $sub = WebSocketSubscription::create([
            'user_id'    => $user->id,
            'channel'    => 'tenant.1.exchange.orderbook',
            'protocol'   => 'x402',
            'amount'     => '1000',
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->deleteJson("/api/websocket/subscriptions/{$sub->id}");

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);

        // Verify expired
        $sub->refresh();
        expect($sub->isActive())->toBeFalse();
    });

    it('returns 404 for non-existent subscription', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $response = $this->deleteJson('/api/websocket/subscriptions/non-existent-id');

        $response->assertNotFound();
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/websocket/subscriptions');

        $response->assertUnauthorized();
    });
});
