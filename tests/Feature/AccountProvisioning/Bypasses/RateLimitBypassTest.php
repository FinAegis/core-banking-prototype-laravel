<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning\Bypasses;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Http\Middleware\ApiRateLimitMiddleware;
use App\Http\Middleware\GraphQLRateLimitMiddleware;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Force rate limiting on in the testing environment.
    config()->set('rate_limiting.enabled', true);
    config()->set('rate_limiting.force_in_tests', true);
    config()->set('rate_limiting.partner_tiers.enabled', false);
    Cache::flush();

    Route::middleware(['api', 'auth:sanctum', ApiRateLimitMiddleware::class . ':auth'])
        ->get('/__test/rate-limited', fn () => response()->json(['ok' => true]));

    Route::middleware(['api', 'auth:sanctum', GraphQLRateLimitMiddleware::class])
        ->get('/__test/graphql-rate-limited', fn () => response()->json(['ok' => true]));
});

it('bypasses rate limit for review accounts with bypass_rate_limit flag', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'           => $user->id,
        'is_review_account' => true,
        'bypass_rate_limit' => true,
    ]);

    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // The 'auth' limit is 5/min + block. Make far more than that to prove bypass works.
    for ($i = 0; $i < 20; $i++) {
        $response = $this->getJson('/__test/rate-limited');
        $response->assertOk();
    }
});

it('enforces rate limit for regular users without bypass', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $gotLimited = false;
    for ($i = 0; $i < 20; $i++) {
        $response = $this->getJson('/__test/rate-limited');
        if ($response->status() === 429) {
            $gotLimited = true;
            break;
        }
    }

    expect($gotLimited)->toBeTrue();
});

it('bypasses GraphQL rate limit for review accounts with bypass_rate_limit flag', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'           => $user->id,
        'is_review_account' => true,
        'bypass_rate_limit' => true,
    ]);

    // Tighten auth limit to 2 to prove the bypass path is hit (bypass accounts
    // would normally trip a 2-request limit immediately).
    config()->set('lighthouse.rate_limiting.auth_limit', 2);

    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    for ($i = 0; $i < 10; $i++) {
        $response = $this->getJson('/__test/graphql-rate-limited');
        $response->assertOk();
    }
});

it('enforces GraphQL rate limit for regular users', function () {
    $user = User::factory()->create();
    config()->set('lighthouse.rate_limiting.auth_limit', 2);

    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // First two go through, third should 429.
    $this->getJson('/__test/graphql-rate-limited')->assertOk();
    $this->getJson('/__test/graphql-rate-limited')->assertOk();
    $this->getJson('/__test/graphql-rate-limited')->assertStatus(429);
});
