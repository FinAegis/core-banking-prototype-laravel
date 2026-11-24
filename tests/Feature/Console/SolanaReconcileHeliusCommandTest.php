<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Services\HeliusWebhookSyncService;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

it('exits successfully with a warning when --auto-sync fixes the drift', function (): void {
    $user = User::factory()->create();

    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => 'address-in-db',
        'public_key' => 'address-in-db',
        'is_active'  => true,
    ]);

    // The sync service is mocked so no Helius write happens; the re-check
    // after the sync sees the address Helius now watches.
    $sync = Mockery::mock(HeliusWebhookSyncService::class);
    $sync->shouldReceive('syncAllAddresses')->once()->andReturn(1);
    app()->instance(HeliusWebhookSyncService::class, $sync);

    Http::fake([
        'https://api.helius.xyz/v0/webhooks/test-webhook-id*' => Http::sequence()
            ->push(['webhookID' => 'test-webhook-id', 'accountAddresses' => []], 200)
            ->push(['webhookID' => 'test-webhook-id', 'accountAddresses' => ['address-in-db']], 200),
    ]);

    $log = Log::spy();

    $this->artisan('solana:reconcile-helius --auto-sync')
        ->expectsOutputToContain('Drift detected')
        ->expectsOutputToContain('Drift fixed by solana:sync')
        ->assertSuccessful();

    $log->shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'drift fixed by auto-sync'))
        ->once();
    $log->shouldNotHaveReceived('error');

    Http::assertSentCount(2);
});

it('exits non-zero when drift remains after --auto-sync', function (): void {
    $user = User::factory()->create();

    BlockchainAddress::create([
        'user_uuid'  => $user->uuid,
        'chain'      => 'solana',
        'address'    => 'address-in-db',
        'public_key' => 'address-in-db',
        'is_active'  => true,
    ]);

    $sync = Mockery::mock(HeliusWebhookSyncService::class);
    $sync->shouldReceive('syncAllAddresses')->once()->andReturn(0);
    app()->instance(HeliusWebhookSyncService::class, $sync);

    Http::fake([
        'https://api.helius.xyz/v0/webhooks/test-webhook-id*' => Http::response([
            'webhookID'        => 'test-webhook-id',
            'accountAddresses' => [],
        ], 200),
    ]);

    $log = Log::spy();

    $this->artisan('solana:reconcile-helius --auto-sync')
        ->expectsOutputToContain('Drift remains after solana:sync')
        ->assertFailed();

    $log->shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'drift remains after auto-sync'))
        ->once();

    Http::assertSentCount(2);
});
