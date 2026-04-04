<?php

declare(strict_types=1);

use App\Domain\Wallet\Services\AlchemyWebhookSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

const ALCHEMY_API_URL = 'https://dashboard.alchemy.com/api/update-webhook-addresses';

beforeEach(function (): void {
    config([
        'services.alchemy.notify_token'      => 'test-alchemy-token',
        'services.alchemy.solana_webhook_id' => 'wh_test123',
    ]);
});

it('adds an address via PATCH API', function (): void {
    Http::fake([
        ALCHEMY_API_URL => Http::response([], 200),
    ]);

    $service = new AlchemyWebhookSyncService();
    $result = $service->addAddress('SomeValidSolanaAddress123');

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === ALCHEMY_API_URL
            && $request->method() === 'PATCH'
            && $request->hasHeader('X-Alchemy-Token', 'test-alchemy-token')
            && $request->data()['webhook_id'] === 'wh_test123'
            && $request->data()['addresses_to_add'] === ['SomeValidSolanaAddress123']
            && $request->data()['addresses_to_remove'] === [];
    });
});

it('removes an address via PATCH API', function (): void {
    Http::fake([
        ALCHEMY_API_URL => Http::response([], 200),
    ]);

    $service = new AlchemyWebhookSyncService();
    $result = $service->removeAddress('SomeValidSolanaAddress123');

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === ALCHEMY_API_URL
            && $request->method() === 'PATCH'
            && $request->data()['webhook_id'] === 'wh_test123'
            && $request->data()['addresses_to_add'] === []
            && $request->data()['addresses_to_remove'] === ['SomeValidSolanaAddress123'];
    });
});

it('rejects reserved Solana addresses without making HTTP calls', function (string $reservedAddress): void {
    Http::fake();

    $service = new AlchemyWebhookSyncService();
    $result = $service->addAddress($reservedAddress);

    expect($result)->toBeFalse();

    Http::assertNothingSent();
})->with([
    '11111111111111111111111111111111',
    '11111111111111111111111111111112',
    'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
    'ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL',
    'SysvarC1ock11111111111111111111111111111111',
]);

it('returns false when not configured with empty token', function (): void {
    config([
        'services.alchemy.notify_token' => '',
    ]);

    Http::fake();

    $service = new AlchemyWebhookSyncService();
    $result = $service->addAddress('SomeValidSolanaAddress123');

    expect($result)->toBeFalse();

    Http::assertNothingSent();
});

it('returns false when not configured with empty webhook id', function (): void {
    config([
        'services.alchemy.solana_webhook_id' => '',
    ]);

    Http::fake();

    $service = new AlchemyWebhookSyncService();
    $result = $service->addAddress('SomeValidSolanaAddress123');

    expect($result)->toBeFalse();

    Http::assertNothingSent();
});

it('returns false when API returns an error', function (): void {
    Http::fake([
        ALCHEMY_API_URL => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $service = new AlchemyWebhookSyncService();
    $result = $service->addAddress('SomeValidSolanaAddress123');

    expect($result)->toBeFalse();
});

it('syncs all active Solana addresses in one PATCH call', function (): void {
    // Create the blockchain_addresses table inline for SQLite (skip if already exists from migrations)
    if (! Schema::hasTable('blockchain_addresses')) {
        Schema::create('blockchain_addresses', function ($table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid');
            $table->string('chain');
            $table->string('address');
            $table->string('public_key')->default('');
            $table->string('derivation_path')->nullable();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    // Seed test data
    App\Domain\Account\Models\BlockchainAddress::create([
        'uuid'       => (string) Illuminate\Support\Str::uuid(),
        'user_uuid'  => (string) Illuminate\Support\Str::uuid(),
        'chain'      => 'solana',
        'address'    => 'UserWallet111',
        'public_key' => 'pk1',
        'is_active'  => true,
    ]);

    App\Domain\Account\Models\BlockchainAddress::create([
        'uuid'       => (string) Illuminate\Support\Str::uuid(),
        'user_uuid'  => (string) Illuminate\Support\Str::uuid(),
        'chain'      => 'solana',
        'address'    => 'UserWallet222',
        'public_key' => 'pk2',
        'is_active'  => true,
    ]);

    // Inactive — should be excluded
    App\Domain\Account\Models\BlockchainAddress::create([
        'uuid'       => (string) Illuminate\Support\Str::uuid(),
        'user_uuid'  => (string) Illuminate\Support\Str::uuid(),
        'chain'      => 'solana',
        'address'    => 'InactiveWallet',
        'public_key' => 'pk3',
        'is_active'  => false,
    ]);

    // Different chain — should be excluded
    App\Domain\Account\Models\BlockchainAddress::create([
        'uuid'       => (string) Illuminate\Support\Str::uuid(),
        'user_uuid'  => (string) Illuminate\Support\Str::uuid(),
        'chain'      => 'ethereum',
        'address'    => 'EthWallet333',
        'public_key' => 'pk4',
        'is_active'  => true,
    ]);

    // Reserved address — should be excluded
    App\Domain\Account\Models\BlockchainAddress::create([
        'uuid'       => (string) Illuminate\Support\Str::uuid(),
        'user_uuid'  => (string) Illuminate\Support\Str::uuid(),
        'chain'      => 'solana',
        'address'    => '11111111111111111111111111111111',
        'public_key' => 'pk5',
        'is_active'  => true,
    ]);

    Http::fake([
        ALCHEMY_API_URL => Http::response([], 200),
    ]);

    $service = new AlchemyWebhookSyncService();
    $count = $service->syncAllAddresses();

    expect($count)->toBe(2);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === ALCHEMY_API_URL
            && $request->method() === 'PATCH'
            && count($data['addresses_to_add']) === 2
            && in_array('UserWallet111', $data['addresses_to_add'], true)
            && in_array('UserWallet222', $data['addresses_to_add'], true)
            && $data['addresses_to_remove'] === [];
    });
});
