<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(Tests\TestCase::class);

it('blocks unverified users from financial endpoints', function () {
    $user = User::factory()->create(['kyc_status' => 'not_started']);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/ramp/session', [
        'type'           => 'on',
        'fiatCurrency'   => 'USD',
        'fiatAmount'     => 100,
        'cryptoCurrency' => 'USDC',
        'walletAddress'  => '0xtest',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('error', 'ERR_KYC_REQUIRED');
});

it('allows verified users through', function () {
    $user = User::factory()->create([
        'kyc_status'      => 'approved',
        'kyc_approved_at' => now(),
    ]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // This will get past the KYC middleware (may return 422 or other error from controller logic, but NOT 403)
    $response = $this->getJson('/api/v1/ramp/supported');
    $response->assertStatus(200);
});

it('allows unverified users to access KYC payment endpoints', function () {
    $user = User::factory()->create(['kyc_status' => 'not_started']);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // KYC payment endpoints should NOT have require.kyc middleware
    // This will return 404 (no application), not 403
    $response = $this->postJson('/api/v1/trustcert/applications/fake-id/pay');
    $response->assertStatus(404); // Not 403 — KYC middleware not applied
});

it('allows unverified users to join card waitlist', function () {
    $user = User::factory()->create(['kyc_status' => 'not_started']);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/cards/waitlist');
    $response->assertStatus(201); // Not 403 — KYC middleware not applied
});
