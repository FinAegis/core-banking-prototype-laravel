<?php

declare(strict_types=1);

use App\Domain\Privacy\Models\RailgunWallet;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Phase 1 of the non-custodial RAILGUN migration: the device creates the 0zk
// wallet locally and registers only its PUBLIC address with the backend (for
// activity-feed mirroring / push / scan hints). The server NEVER receives or
// stores seed material — encrypted_mnemonic stays null for registered wallets.

// bech32m-shaped public RAILGUN address (no secret); length 114 ≤ column max 128.
function validRailgunAddress(): string
{
    return '0zk1' . str_repeat('q', 110);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['read', 'write', 'delete']);
});

it('registers a non-custodial wallet and stores NO seed material', function () {
    $response = $this->postJson('/api/v1/privacy/wallet/register', [
        'railgun_address' => validRailgunAddress(),
        'network'         => 'polygon',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.railgun_address', validRailgunAddress())
        ->assertJsonPath('data.network', 'polygon');

    $wallet = RailgunWallet::where('railgun_address', validRailgunAddress())->firstOrFail();
    expect($wallet->user_id)->toBe($this->user->id)
        ->and($wallet->getAttributes()['encrypted_mnemonic'] ?? null)->toBeNull(); // raw column — no server-held seed
});

it('is idempotent — re-registering updates the network without duplicating', function () {
    $this->postJson('/api/v1/privacy/wallet/register', ['railgun_address' => validRailgunAddress(), 'network' => 'polygon'])->assertOk();
    $this->postJson('/api/v1/privacy/wallet/register', ['railgun_address' => validRailgunAddress(), 'network' => 'arbitrum'])->assertOk();

    $wallets = RailgunWallet::where('railgun_address', validRailgunAddress())->get();
    expect($wallets)->toHaveCount(1)
        ->and($wallets->first()->network)->toBe('arbitrum');
});

it('rejects a malformed 0zk address', function () {
    $this->postJson('/api/v1/privacy/wallet/register', [
        'railgun_address' => 'not-a-railgun-address',
        'network'         => 'polygon',
    ])->assertStatus(422)->assertJsonValidationErrors('railgun_address');
});

it('rejects an unsupported network', function () {
    $this->postJson('/api/v1/privacy/wallet/register', [
        'railgun_address' => validRailgunAddress(),
        'network'         => 'base', // RAILGUN does not support Base
    ])->assertStatus(422)->assertJsonValidationErrors('network');
});

it('refuses to register an address already owned by another user', function () {
    $other = User::factory()->create();
    RailgunWallet::create([
        'user_id'         => $other->id,
        'railgun_address' => validRailgunAddress(),
        'network'         => 'polygon',
        'status'          => 'active',
    ]);

    $this->postJson('/api/v1/privacy/wallet/register', [
        'railgun_address' => validRailgunAddress(),
        'network'         => 'polygon',
    ])->assertStatus(409)->assertJsonPath('error.code', 'ADDRESS_ALREADY_REGISTERED');
});

it('requires authentication', function () {
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/privacy/wallet/register', [
        'railgun_address' => validRailgunAddress(),
        'network'         => 'polygon',
    ])->assertUnauthorized();
});
