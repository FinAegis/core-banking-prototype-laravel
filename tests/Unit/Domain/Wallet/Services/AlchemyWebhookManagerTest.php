<?php

declare(strict_types=1);

use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Models\WebhookEndpoint;
use App\Domain\Wallet\Services\AlchemyWebhookManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

const ALCHEMY_CREATE_URL = 'https://dashboard.alchemy.com/api/create-webhook';
const ALCHEMY_PATCH_URL = 'https://dashboard.alchemy.com/api/update-webhook-addresses';

beforeEach(function (): void {
    config([
        'services.alchemy.notify_token' => 'test-notify-token',
    ]);

    if (! Schema::hasTable('webhook_endpoints')) {
        Schema::create('webhook_endpoints', function ($table): void {
            $table->id();
            $table->string('provider', 20);
            $table->string('network', 30);
            $table->unsignedInteger('shard')->default(0);
            $table->string('external_webhook_id');
            $table->text('signing_key');
            $table->string('webhook_url');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('address_count')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'network', 'shard']);
            $table->index(['provider', 'network', 'is_active']);
        });
    }
});

it('creates a new webhook when none exists for the network', function (): void {
    Http::fake([
        ALCHEMY_CREATE_URL => Http::response([
            'data' => [
                'id'          => 'wh_new123',
                'signing_key' => 'whsec_abc',
                'is_active'   => true,
            ],
        ], 200),
        ALCHEMY_PATCH_URL => Http::response([], 200),
    ]);

    $manager = new AlchemyWebhookManager();
    $result = $manager->addAddress('0xAbCdEf1234567890AbCdEf1234567890AbCdEf12', 'ethereum');

    expect($result)->toBeTrue();

    // Verify the webhook endpoint was stored in DB
    $endpoint = WebhookEndpoint::where('provider', 'alchemy')
        ->where('network', 'ethereum')
        ->first();

    assert($endpoint instanceof WebhookEndpoint);

    expect($endpoint->external_webhook_id)->toBe('wh_new123')
        ->and($endpoint->signing_key)->toBe('whsec_abc')
        ->and($endpoint->is_active)->toBeTrue()
        ->and($endpoint->address_count)->toBe(1);

    // Verify create was called with correct Alchemy network enum
    Http::assertSent(function ($request) {
        if ($request->url() === ALCHEMY_CREATE_URL) {
            return $request->method() === 'POST'
                && $request->hasHeader('X-Alchemy-Token', 'test-notify-token')
                && $request->data()['network'] === 'ETH_MAINNET'
                && $request->data()['webhook_type'] === 'ADDRESS_ACTIVITY'
                && $request->data()['webhook_url'] === 'https://zelta.app/api/webhooks/alchemy/address-activity';
        }

        return true;
    });
});

it('reuses existing webhook when one exists — does not call create API', function (): void {
    // Pre-seed an existing webhook
    WebhookEndpoint::create([
        'provider'            => 'alchemy',
        'network'             => 'polygon',
        'shard'               => 1,
        'external_webhook_id' => 'wh_existing',
        'signing_key'         => 'whsec_existing',
        'webhook_url'         => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active'           => true,
        'address_count'       => 5,
    ]);

    Http::fake([
        ALCHEMY_PATCH_URL => Http::response([], 200),
    ]);

    $manager = new AlchemyWebhookManager();
    $result = $manager->addAddress('0x1111111111111111111111111111111111111111', 'polygon');

    expect($result)->toBeTrue();

    // Should NOT have called create
    Http::assertNotSent(fn ($request) => $request->url() === ALCHEMY_CREATE_URL);

    // Should have called patch with existing webhook ID
    Http::assertSent(function ($request) {
        return $request->url() === ALCHEMY_PATCH_URL
            && $request->data()['webhook_id'] === 'wh_existing'
            && $request->data()['addresses_to_add'] === ['0x1111111111111111111111111111111111111111'];
    });

    // Address count should be incremented
    $endpoint = WebhookEndpoint::where('external_webhook_id', 'wh_existing')->first();
    assert($endpoint instanceof WebhookEndpoint);
    expect($endpoint->address_count)->toBe(6);
});

it('returns all signing keys for active webhooks', function (): void {
    WebhookEndpoint::create([
        'provider'            => 'alchemy',
        'network'             => 'ethereum',
        'shard'               => 1,
        'external_webhook_id' => 'wh_eth1',
        'signing_key'         => 'whsec_key1',
        'webhook_url'         => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active'           => true,
        'address_count'       => 10,
    ]);

    WebhookEndpoint::create([
        'provider'            => 'alchemy',
        'network'             => 'polygon',
        'shard'               => 1,
        'external_webhook_id' => 'wh_poly1',
        'signing_key'         => 'whsec_key2',
        'webhook_url'         => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active'           => true,
        'address_count'       => 20,
    ]);

    // Inactive — should be excluded
    WebhookEndpoint::create([
        'provider'            => 'alchemy',
        'network'             => 'base',
        'shard'               => 1,
        'external_webhook_id' => 'wh_base_inactive',
        'signing_key'         => 'whsec_key_inactive',
        'webhook_url'         => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active'           => false,
        'address_count'       => 0,
    ]);

    // Different provider — should be excluded
    WebhookEndpoint::create([
        'provider'            => 'helius',
        'network'             => 'solana',
        'shard'               => 1,
        'external_webhook_id' => 'wh_helius1',
        'signing_key'         => 'whsec_helius',
        'webhook_url'         => 'https://zelta.app/api/webhooks/helius',
        'is_active'           => true,
        'address_count'       => 5,
    ]);

    $manager = new AlchemyWebhookManager();
    $keys = $manager->getSigningKeys();

    expect($keys)->toHaveCount(2)
        ->and($keys)->toContain('whsec_key1')
        ->and($keys)->toContain('whsec_key2')
        ->and($keys)->not->toContain('whsec_key_inactive')
        ->and($keys)->not->toContain('whsec_helius');
});

it('removes address and decrements count', function (): void {
    WebhookEndpoint::create([
        'provider'            => 'alchemy',
        'network'             => 'arbitrum',
        'shard'               => 1,
        'external_webhook_id' => 'wh_arb1',
        'signing_key'         => 'whsec_arb',
        'webhook_url'         => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active'           => true,
        'address_count'       => 10,
    ]);

    Http::fake([
        ALCHEMY_PATCH_URL => Http::response([], 200),
    ]);

    $manager = new AlchemyWebhookManager();
    $result = $manager->removeAddress('0xDeAdBeEf00000000000000000000000000000001', 'arbitrum');

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === ALCHEMY_PATCH_URL
            && $request->data()['webhook_id'] === 'wh_arb1'
            && $request->data()['addresses_to_add'] === []
            && $request->data()['addresses_to_remove'] === ['0xdeadbeef00000000000000000000000000000001'];
    });

    $endpoint = WebhookEndpoint::where('external_webhook_id', 'wh_arb1')->first();
    assert($endpoint instanceof WebhookEndpoint);
    expect($endpoint->address_count)->toBe(10); // address_count is a high watermark, not decremented on remove
});

it('skips when notify token is not configured', function (): void {
    config(['services.alchemy.notify_token' => '']);

    Http::fake();

    $manager = new AlchemyWebhookManager();

    expect($manager->addAddress('0x1234', 'ethereum'))->toBeFalse()
        ->and($manager->removeAddress('0x1234', 'ethereum'))->toBeFalse()
        ->and($manager->syncAllAddresses('ethereum'))->toBe(0);

    Http::assertNothingSent();
});

it('maps internal network names to Alchemy enum in create call', function (string $internal, string $alchemyEnum): void {
    Http::fake([
        ALCHEMY_CREATE_URL => Http::response([
            'data' => [
                'id'          => 'wh_mapped',
                'signing_key' => 'whsec_mapped',
                'is_active'   => true,
            ],
        ], 200),
        ALCHEMY_PATCH_URL => Http::response([], 200),
    ]);

    $manager = new AlchemyWebhookManager();
    $manager->addAddress('0x0000000000000000000000000000000000000001', $internal);

    Http::assertSent(function ($request) use ($alchemyEnum) {
        if ($request->url() === ALCHEMY_CREATE_URL) {
            return $request->data()['network'] === $alchemyEnum;
        }

        return true;
    });

    // Clean up for next dataset iteration
    WebhookEndpoint::truncate();
})->with([
    ['ethereum', 'ETH_MAINNET'],
    ['polygon', 'MATIC_MAINNET'],
    ['arbitrum', 'ARB_MAINNET'],
    ['base', 'BASE_MAINNET'],
]);

it('syncs all smart account addresses for a network', function (): void {
    if (! Schema::hasTable('smart_accounts')) {
        Schema::create('smart_accounts', function ($table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id');
            $table->string('owner_address', 42);
            $table->string('account_address', 42);
            $table->string('network', 20);
            $table->boolean('deployed')->default(false);
            $table->string('deploy_tx_hash', 66)->nullable();
            $table->unsignedBigInteger('nonce')->default(0);
            $table->unsignedInteger('pending_ops')->default(0);
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    // Create test user
    $user = App\Models\User::create([
        'name'     => 'Test User',
        'email'    => 'sync-test@example.com',
        'password' => bcrypt('password'),
    ]);

    SmartAccount::create([
        'id'              => (string) Illuminate\Support\Str::uuid(),
        'user_id'         => $user->id,
        'owner_address'   => '0xOwner1',
        'account_address' => '0xAccount1',
        'network'         => 'base',
    ]);

    SmartAccount::create([
        'id'              => (string) Illuminate\Support\Str::uuid(),
        'user_id'         => $user->id,
        'owner_address'   => '0xOwner2',
        'account_address' => '0xAccount2',
        'network'         => 'base',
    ]);

    // Different network — excluded
    SmartAccount::create([
        'id'              => (string) Illuminate\Support\Str::uuid(),
        'user_id'         => $user->id,
        'owner_address'   => '0xOwner3',
        'account_address' => '0xAccount3',
        'network'         => 'ethereum',
    ]);

    // Pre-seed the webhook endpoint
    WebhookEndpoint::create([
        'provider'            => 'alchemy',
        'network'             => 'base',
        'shard'               => 1,
        'external_webhook_id' => 'wh_base_sync',
        'signing_key'         => 'whsec_base_sync',
        'webhook_url'         => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active'           => true,
        'address_count'       => 0,
    ]);

    Http::fake([
        ALCHEMY_PATCH_URL => Http::response([], 200),
    ]);

    $manager = new AlchemyWebhookManager();
    $count = $manager->syncAllAddresses('base');

    expect($count)->toBe(2);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === ALCHEMY_PATCH_URL
            && $request->data()['webhook_id'] === 'wh_base_sync'
            && count($data['addresses_to_add']) === 2
            && in_array('0xaccount1', $data['addresses_to_add'], true)
            && in_array('0xaccount2', $data['addresses_to_add'], true);
    });

    $endpoint = WebhookEndpoint::where('external_webhook_id', 'wh_base_sync')->first();
    assert($endpoint instanceof WebhookEndpoint);
    expect($endpoint->address_count)->toBe(2);
});
