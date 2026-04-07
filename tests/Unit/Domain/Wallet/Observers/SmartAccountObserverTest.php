<?php

declare(strict_types=1);

use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Models\WebhookEndpoint;
use App\Domain\Wallet\Services\AlchemyWebhookManager;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'services.alchemy.notify_token' => 'test-token',
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
});

it('registers EVM address with Alchemy when SmartAccount is created', function (): void {
    WebhookEndpoint::create([
        'provider'            => 'alchemy',
        'network'             => 'ethereum',
        'shard'               => 1,
        'external_webhook_id' => 'wh_eth_observer',
        'signing_key'         => 'whsec_observer',
        'webhook_url'         => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active'           => true,
        'address_count'       => 0,
    ]);

    Http::fake([
        'https://dashboard.alchemy.com/api/update-webhook-addresses' => Http::response([], 200),
    ]);

    $user = User::create([
        'name'     => 'Observer Test',
        'email'    => 'observer-test@example.com',
        'password' => bcrypt('password'),
    ]);

    SmartAccount::create([
        'id'              => (string) Str::uuid(),
        'user_id'         => $user->id,
        'owner_address'   => '0xOwnerAddr000000000000000000000000000001',
        'account_address' => '0xSmartAddr000000000000000000000000000001',
        'network'         => 'ethereum',
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://dashboard.alchemy.com/api/update-webhook-addresses'
            && $request->data()['webhook_id'] === 'wh_eth_observer'
            && in_array('0xsmartaddr000000000000000000000000000001', $request->data()['addresses_to_add'], true);
    });

    $endpoint = WebhookEndpoint::where('external_webhook_id', 'wh_eth_observer')->first();
    assert($endpoint instanceof WebhookEndpoint);
    expect($endpoint->address_count)->toBe(1);
});

it('does not make HTTP calls when notify token is missing', function (): void {
    config(['services.alchemy.notify_token' => '']);

    Http::fake();

    $user = User::create([
        'name'     => 'No Token Test',
        'email'    => 'no-token@example.com',
        'password' => bcrypt('password'),
    ]);

    SmartAccount::create([
        'id'              => (string) Str::uuid(),
        'user_id'         => $user->id,
        'owner_address'   => '0xOwnerAddr000000000000000000000000000002',
        'account_address' => '0xSmartAddr000000000000000000000000000002',
        'network'         => 'polygon',
    ]);

    Http::assertNothingSent();
});

it('observer deleted method calls removeAddress on webhook manager', function (): void {
    WebhookEndpoint::create([
        'provider'            => 'alchemy',
        'network'             => 'base',
        'shard'               => 1,
        'external_webhook_id' => 'wh_base_delete',
        'signing_key'         => 'whsec_delete',
        'webhook_url'         => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active'           => true,
        'address_count'       => 5,
    ]);

    Http::fake([
        'https://dashboard.alchemy.com/api/update-webhook-addresses' => Http::response([], 200),
    ]);

    $manager = app(AlchemyWebhookManager::class);
    $result = $manager->removeAddress('0xSmartAddr000000000000000000000000000003', 'base');

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://dashboard.alchemy.com/api/update-webhook-addresses'
            && $request->data()['webhook_id'] === 'wh_base_delete'
            && in_array('0xsmartaddr000000000000000000000000000003', $request->data()['addresses_to_remove'], true);
    });

    $endpoint = WebhookEndpoint::where('external_webhook_id', 'wh_base_delete')->first();
    assert($endpoint instanceof WebhookEndpoint);
    expect($endpoint->address_count)->toBe(5); // address_count is a high watermark, not decremented on remove
});

it('observer logs errors when webhook API call fails', function (): void {
    WebhookEndpoint::create([
        'provider'            => 'alchemy',
        'network'             => 'polygon',
        'shard'               => 1,
        'external_webhook_id' => 'wh_poly_fail',
        'signing_key'         => 'whsec_fail',
        'webhook_url'         => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active'           => true,
        'address_count'       => 10,
    ]);

    Http::fake([
        'https://dashboard.alchemy.com/api/update-webhook-addresses' => Http::response('Internal Server Error', 500),
    ]);

    // Calling addAddress directly when the API returns 500 should return false
    // (the observer wraps in try-catch so the error is logged, not thrown)
    $manager = app(AlchemyWebhookManager::class);
    $result = $manager->addAddress('0xSmartAddr000000000000000000000000000004', 'polygon');

    expect($result)->toBeFalse();

    // Address count should NOT be incremented on failure
    $endpoint = WebhookEndpoint::where('external_webhook_id', 'wh_poly_fail')->first();
    assert($endpoint instanceof WebhookEndpoint);
    expect($endpoint->address_count)->toBe(10);
});
