<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\Traits\CreatesSolanaTestTables;

uses(CreatesSolanaTestTables::class);

beforeEach(function (): void {
    config([
        'cache.default'              => 'array',
        'services.helius.webhook_id' => 'test-webhook-id',
        'services.helius.api_key'    => 'test-api-key',
    ]);

    $this->createSolanaTestTables();
});

afterEach(function (): void {
    $this->dropSolanaTestTables();
});

it('reports success when DB and Helius addresses match', function (): void {
    $user = User::factory()->create();
    $address = '9UYjpLnTu78B5kqNoKgghLTcRKsUUZNRGUfxEZiwpR7L';

    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => $address,
        'public_key' => $address,
        'is_active'  => true,
    ]);

    Http::fake([
        'https://api.helius.xyz/v0/webhooks/test-webhook-id*' => Http::response([
            'webhookID'        => 'test-webhook-id',
            'accountAddresses' => [$address],
        ], 200),
    ]);

    $this->artisan('solana:reconcile-helius')
        ->expectsOutputToContain('In sync')
        ->assertSuccessful();
});

it('exits non-zero when DB has addresses Helius does not watch', function (): void {
    $user = User::factory()->create();

    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => 'address-in-db',
        'public_key' => 'address-in-db',
        'is_active'  => true,
    ]);

    Http::fake([
        'https://api.helius.xyz/v0/webhooks/test-webhook-id*' => Http::response([
            'webhookID'        => 'test-webhook-id',
            'accountAddresses' => [],
        ], 200),
    ]);

    $this->artisan('solana:reconcile-helius')
        ->expectsOutputToContain('Drift detected')
        ->expectsOutputToContain('address-in-db')
        ->assertFailed();
});

it('reports drift when Helius watches addresses not in our DB', function (): void {
    Http::fake([
        'https://api.helius.xyz/v0/webhooks/test-webhook-id*' => Http::response([
            'webhookID'        => 'test-webhook-id',
            'accountAddresses' => ['orphan-address'],
        ], 200),
    ]);

    $this->artisan('solana:reconcile-helius')
        ->expectsOutputToContain('Orphaned on Helius')
        ->expectsOutputToContain('orphan-address')
        ->assertFailed();
});

it('skips silently when Helius is not configured', function (): void {
    config([
        'services.helius.webhook_id' => '',
        'services.helius.api_key'    => '',
    ]);

    $this->artisan('solana:reconcile-helius')
        ->expectsOutputToContain('not configured')
        ->assertSuccessful();
});
