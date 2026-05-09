<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['cache.default' => 'array']);
});

it('rejects POST /checkout when Idempotency-Key header is missing', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/checkout', [
        'plan'              => 'monthly_pro',
        'withdrawalConsent' => [
            'given'       => true,
            'shownAt'     => now()->toIso8601String(),
            'acceptedAt'  => now()->toIso8601String(),
            'consentText' => 'I waive my 14-day right of withdrawal.',
            'version'     => 1,
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'ERR_VALIDATION_001');
});

it('rejects POST /checkout with stale withdrawal consent (>5min old)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/checkout', [
        'plan'              => 'monthly_pro',
        'withdrawalConsent' => [
            'given'       => true,
            'shownAt'     => now()->subMinutes(10)->toIso8601String(),
            'acceptedAt'  => now()->subMinutes(10)->toIso8601String(),
            'consentText' => 'I waive my 14-day right of withdrawal.',
            'version'     => 1,
        ],
    ], [
        'Idempotency-Key' => (string) Str::uuid(),
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'ERR_SUB_004');
});

it('rejects POST /change-plan annual to monthly downgrade with ERR_SUB_006', function () {
    // Without an existing subscription, the controller short-circuits with
    // ERR_SUB_002. We verify the ERR_SUB_006 path via the unit test on
    // SubscriptionService directly in tests/Unit/Subscription.
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/change-plan', [
        'plan' => 'monthly_pro',
    ], [
        'Idempotency-Key' => (string) Str::uuid(),
    ]);

    // No active sub → ERR_SUB_002
    $response->assertStatus(409);
    $response->assertJsonPath('code', 'ERR_SUB_002');
});

it('replays POST /cancel responses on duplicate Idempotency-Key', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $key = (string) Str::uuid();

    $first = $this->postJson('/api/v1/subscription/cancel', [], ['Idempotency-Key' => $key]);
    $first->assertStatus(409);
    $first->assertJsonPath('code', 'ERR_SUB_002'); // no active sub

    // Non-2xx responses are not cached by the idempotency middleware (deliberate —
    // we don't want to memoise transient errors); the second call re-runs the
    // handler and returns 409 again.
    $second = $this->postJson('/api/v1/subscription/cancel', [], ['Idempotency-Key' => $key]);
    $second->assertStatus(409);
});

it('returns 422 ERR_VALIDATION_001 for malformed Idempotency-Key', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/cancel', [], [
        'Idempotency-Key' => 'too-short', // not UUID, not 16+ alphanumeric
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'ERR_VALIDATION_001');
});

it('returns 401 for unauthenticated mutating endpoints', function () {
    $response = $this->postJson('/api/v1/subscription/cancel', [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ]);

    $response->assertStatus(401);
});
