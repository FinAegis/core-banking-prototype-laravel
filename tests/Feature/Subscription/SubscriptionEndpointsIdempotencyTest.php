<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
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
    $response->assertJsonPath('error.code', 'ERR_VALIDATION_001');
});

it('rejects POST /checkout with malformed (too-short) Idempotency-Key', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/cancel', [], [
        'Idempotency-Key' => 'too-short',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION_003');
});

it('rejects POST /checkout with stale withdrawal consent (>5 minutes old) — ERR_SUB_004', function () {
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
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_SUB_004');
});

it('returns ERR_SUB_002 for change-plan when no active subscription exists', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/change-plan', [
        'plan' => 'monthly_pro',
    ], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error.code', 'ERR_SUB_002');
});

it('replays POST /cancel responses on duplicate Idempotency-Key (4xx is cached)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $key = Str::random(24);

    $first = $this->postJson('/api/v1/subscription/cancel', [], ['Idempotency-Key' => $key]);
    $first->assertStatus(409);
    $first->assertJsonPath('error.code', 'ERR_SUB_002');

    // 4xx is cached; second call replays the same response.
    $second = $this->postJson('/api/v1/subscription/cancel', [], ['Idempotency-Key' => $key]);
    $second->assertStatus(409);
    $second->assertHeader('X-Idempotency-Replayed', 'true');
});

it('returns 401 for unauthenticated mutating endpoints', function () {
    $response = $this->postJson('/api/v1/subscription/cancel', [], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertStatus(401);
});
