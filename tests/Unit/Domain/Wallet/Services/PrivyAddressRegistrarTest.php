<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Services\PrivyAddressRegistrar;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\CreatesSolanaTestTables;

uses(TestCase::class, CreatesSolanaTestTables::class);

beforeEach(function (): void {
    $this->createSolanaTestTables();
    $this->registrar = new PrivyAddressRegistrar();
});

afterEach(function (): void {
    $this->dropSolanaTestTables();
});

it('writes one row per EVM chain plus one Solana row on first registration', function (): void {
    $user = User::factory()->create();

    $records = $this->registrar->register(
        user: $user,
        evm: [
            'address'                     => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'owner_passkey_credential_id' => 'cred_abc123',
        ],
        solana: ['address' => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z'],
    );

    expect($records)->toHaveCount(5);

    $chains = BlockchainAddress::where('user_uuid', $user->uuid)
        ->pluck('chain')
        ->sort()
        ->values()
        ->all();

    expect($chains)->toBe(['arbitrum', 'base', 'ethereum', 'polygon', 'solana']);
});

it('lowercases EVM address and stores it identically on every EVM chain', function (): void {
    $user = User::factory()->create();

    $this->registrar->register(
        user: $user,
        evm: ['address' => '0x742D35CC6634C0532925A3B844BC454E4438F44E'],
        solana: ['address' => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z'],
    );

    $evmRows = BlockchainAddress::where('user_uuid', $user->uuid)
        ->whereIn('chain', ['polygon', 'base', 'arbitrum', 'ethereum'])
        ->pluck('address')
        ->unique()
        ->values()
        ->all();

    expect($evmRows)->toBe(['0x742d35cc6634c0532925a3b844bc454e4438f44e']);
});

it('stores provider metadata for downstream gating', function (): void {
    $user = User::factory()->create();

    $this->registrar->register(
        user: $user,
        evm: [
            'address'                     => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'owner_passkey_credential_id' => 'cred_xyz',
        ],
        solana: ['address' => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z'],
    );

    $polygon = BlockchainAddress::where('user_uuid', $user->uuid)
        ->where('chain', 'polygon')
        ->first();
    expect($polygon->metadata['provider'])->toBe('privy')
        ->and($polygon->metadata['wallet_kind'])->toBe('privy_smart_account')
        ->and($polygon->metadata['owner_passkey_credential_id'])->toBe('cred_xyz');

    $solana = BlockchainAddress::where('user_uuid', $user->uuid)
        ->where('chain', 'solana')
        ->first();
    expect($solana->metadata['provider'])->toBe('privy')
        ->and($solana->metadata['wallet_kind'])->toBe('privy_embedded_solana');
});

it('is idempotent: registering the same addresses twice does not duplicate rows', function (): void {
    $user = User::factory()->create();

    $this->registrar->register(
        user: $user,
        evm: ['address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'],
        solana: ['address' => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z'],
    );

    $this->registrar->register(
        user: $user,
        evm: ['address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'],
        solana: ['address' => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z'],
    );

    expect(BlockchainAddress::where('user_uuid', $user->uuid)->count())->toBe(5);
});

it('rejects EVM address that does not match the 0x40-hex format', function () {
    $user = User::factory()->create();

    $this->registrar->register(
        user: $user,
        evm: ['address' => '0xnothex'],
        solana: ['address' => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z'],
    );
})->throws(InvalidArgumentException::class, 'Invalid EVM address');

it('rejects Solana address that is not valid base58 in the expected length range', function () {
    $user = User::factory()->create();

    $this->registrar->register(
        user: $user,
        evm: ['address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'],
        solana: ['address' => 'too-short'],
    );
})->throws(InvalidArgumentException::class, 'Invalid Solana');

it('refuses to take over an address already registered to a different user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->registrar->register(
        user: $userA,
        evm: ['address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'],
        solana: ['address' => 'EfkncjQTojTB6m9DqoyBqizLLwZgLu1uwg3Y3FqE6f7Z'],
    );

    $this->registrar->register(
        user: $userB,
        evm: ['address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'],
        solana: ['address' => 'BPFLoaderUpgradeab1e11111111111111111111111'],
    );
})->throws(InvalidArgumentException::class, 'already registered to a different user');
