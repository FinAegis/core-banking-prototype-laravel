<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

it('returns the free-tier shape with HTTP 200 when the user has no subscription (mobile P0)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->getJson('/api/v1/subscription/me');

    $response->assertStatus(200);
    $response->assertExactJson([
        'tier'                 => 'free',
        'status'               => null,
        'source'               => null,
        'plan'                 => null,
        'currentPeriodEnd'     => null,
        'trialEndsAt'          => null,
        'cancelledAtPeriodEnd' => false,
        'pausedUntil'          => null,
    ]);
});

it('returns 401 for unauthenticated callers', function () {
    $response = $this->getJson('/api/v1/subscription/me');

    $response->assertStatus(401);
});
