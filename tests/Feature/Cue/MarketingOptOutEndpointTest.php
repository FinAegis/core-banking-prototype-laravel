<?php

/**
 * MarketingOptOutEndpointTest — Feature tests for POST /api/v1/me/marketing-opt-out.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.8
 */

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

it('sets pro_marketing_opt_out to true and returns 200', function () {
    $user = User::factory()->create(['pro_marketing_opt_out' => false]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/me/marketing-opt-out', ['optOut' => true]);

    $response->assertOk();
    $data = $response->json();
    expect($data['proMarketingOptOut'])->toBeTrue();

    $user->refresh();
    expect((bool) $user->pro_marketing_opt_out)->toBeTrue();
});

it('can set pro_marketing_opt_out back to false', function () {
    $user = User::factory()->create(['pro_marketing_opt_out' => true]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/me/marketing-opt-out', ['optOut' => false]);

    $response->assertOk();
    expect($response->json('proMarketingOptOut'))->toBeFalse();

    $user->refresh();
    expect((bool) $user->pro_marketing_opt_out)->toBeFalse();
});

it('defaults optOut to true when body is empty', function () {
    $user = User::factory()->create(['pro_marketing_opt_out' => false]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/me/marketing-opt-out', []);

    $response->assertOk();
    expect($response->json('proMarketingOptOut'))->toBeTrue();
});

it('returns 401 without auth', function () {
    $response = $this->postJson('/api/v1/me/marketing-opt-out', ['optOut' => true]);
    $response->assertUnauthorized();
});
