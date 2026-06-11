<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Array cache keeps the rate-limiter counters process-local so parallel
    // test workers cannot contaminate each other (fresh per test, no clearing
    // needed — and any models are created before the limiter is touched).
    config(['cache.default' => 'array']);
});

/**
 * Unique Idempotency-Key per request so the idempotency middleware never
 * replays a cached response before the throttle middleware runs.
 */
function iapThrottleIdempotencyKey(string $marker, int $i): string
{
    return sprintf('iap-throttle-%s-%02d-aaaaaaaaaaaaaaaa', $marker, $i);
}

// The request payload is intentionally incomplete (missing receipt/productId)
// so each call terminates as a cheap 422 in validation — the throttle
// middleware has already counted the hit by then, which is all these tests
// care about.
it('throttles POST /api/v1/subscription/iap/verify at 10 requests per minute', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    foreach (range(1, 10) as $i) {
        $response = $this->postJson('/api/v1/subscription/iap/verify', [
            'platform' => 'google_play',
        ], [
            'Idempotency-Key' => iapThrottleIdempotencyKey('base', $i),
        ]);

        expect($response->status())->not->toBe(429, "request {$i} of 10 must not be throttled");
    }

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform' => 'google_play',
    ], [
        'Idempotency-Key' => iapThrottleIdempotencyKey('base', 11),
    ]);

    $response->assertStatus(429);
    expect($response->headers->has('Retry-After'))->toBeTrue();
});

it('scopes the iap/verify throttle per user', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Sanctum::actingAs($userA, ['read', 'write', 'delete']);
    foreach (range(1, 11) as $i) {
        $response = $this->postJson('/api/v1/subscription/iap/verify', [
            'platform' => 'google_play',
        ], [
            'Idempotency-Key' => iapThrottleIdempotencyKey('usera', $i),
        ]);
    }
    $response->assertStatus(429);

    // A different user is keyed independently and is not throttled.
    Sanctum::actingAs($userB, ['read', 'write', 'delete']);
    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform' => 'google_play',
    ], [
        'Idempotency-Key' => iapThrottleIdempotencyKey('userb', 1),
    ]);

    expect($response->status())->not->toBe(429);
});
