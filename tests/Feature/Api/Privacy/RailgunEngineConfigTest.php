<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Phase 1: device-facing bootstrap for the on-device RAILGUN engine. The mobile
// app calls GET /engine-config to configure startRailgunEngine + loadProvider
// against OUR self-hosted infra (POI node, artifact mirror, RPC). Shapes mirror
// the @railgun-community SDK exactly (FallbackProviderJsonConfig, NetworkName,
// poiNodeURLs[]) so the payload is directly consumable.

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

    config([
        'privacy.railgun.networks'                 => ['ethereum', 'polygon', 'arbitrum', 'bsc'],
        'privacy.railgun.engine.wallet_source'     => 'zelta',
        'privacy.railgun.engine.artifact_base_url' => 'https://cdn.zelta.app/railgun-artifacts',
        'privacy.railgun.engine.poi_node_urls'     => ['https://poi.zelta.app'],
        'privacy.railgun.engine.rpc_urls'          => [
            'ethereum' => '',                              // not configured → omitted
            'polygon'  => 'https://rpc.zelta.app/polygon',
            'arbitrum' => 'https://rpc.zelta.app/arbitrum',
            'bsc'      => 'https://rpc.zelta.app/bsc',
        ],
    ]);
});

it('returns the on-device engine bootstrap config', function () {
    $response = $this->getJson('/api/v1/privacy/engine-config');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.wallet_source', 'zelta')
        ->assertJsonPath('data.txid_version', 'V2_PoseidonMerkle')
        ->assertJsonPath('data.use_native_artifacts', true)
        ->assertJsonPath('data.artifact_base_url', 'https://cdn.zelta.app/railgun-artifacts')
        ->assertJsonPath('data.poi_node_urls', ['https://poi.zelta.app']);
});

it('emits a SDK-shaped fallback_provider_config per configured network', function () {
    $data = $this->getJson('/api/v1/privacy/engine-config')->json('data');

    $polygon = collect($data['networks'])->firstWhere('key', 'polygon');
    expect($polygon)->not->toBeNull()
        ->and($polygon['network_name'])->toBe('Polygon')
        ->and($polygon['chain_id'])->toBe(137)
        ->and($polygon['fallback_provider_config']['chainId'])->toBe(137)
        ->and($polygon['fallback_provider_config']['providers'][0]['provider'])->toBe('https://rpc.zelta.app/polygon')
        ->and($polygon['fallback_provider_config']['providers'][0])->toHaveKeys(['priority', 'weight', 'provider']);
});

it('maps bsc to the SDK NetworkName BNB_Chain / chain 56', function () {
    $data = $this->getJson('/api/v1/privacy/engine-config')->json('data');

    $bsc = collect($data['networks'])->firstWhere('key', 'bsc');
    expect($bsc['network_name'])->toBe('BNB_Chain')
        ->and($bsc['chain_id'])->toBe(56);
});

it('omits networks that have no configured RPC URL', function () {
    $data = $this->getJson('/api/v1/privacy/engine-config')->json('data');

    $keys = collect($data['networks'])->pluck('key')->all();
    expect($keys)->not->toContain('ethereum') // rpc url was empty
        ->and($keys)->toContain('polygon');
});

it('omits a network whose RPC URL appears to embed a credential (key-safety guard)', function () {
    config(['privacy.railgun.engine.rpc_urls' => [
        'ethereum' => 'https://eth-mainnet.g.alchemy.com/v2/SECRETKEY1234567890abcdef1234', // key in path
        'polygon'  => 'https://rpc.zelta.app/polygon',                                       // safe proxy
        'arbitrum' => 'https://rpc.example.com/arb?apikey=leakedsecret',                      // key in query
        'bsc'      => '',
    ]]);

    $data = $this->getJson('/api/v1/privacy/engine-config')->json('data');

    // Only the key-safe proxy URL is served; credential-bearing URLs are dropped.
    expect(collect($data['networks'])->pluck('key')->all())->toBe(['polygon']);
});

it('returns a signed RPC proxy URL (not the upstream key) when an upstream is configured', function () {
    config(['privacy.railgun.engine.rpc_upstream' => ['polygon' => 'https://upstream.example/SECRETKEY']]);

    $data = $this->getJson('/api/v1/privacy/engine-config')->json('data');
    $provider = collect($data['networks'])->firstWhere('key', 'polygon')['fallback_provider_config']['providers'][0]['provider'];

    expect($provider)->toContain('/api/v1/privacy/rpc/polygon')
        ->and($provider)->toContain('signature=')
        ->and($provider)->not->toContain('upstream.example')   // server-side key never leaks
        ->and($provider)->not->toContain('SECRETKEY');
});

it('caps the signed proxy URL TTL even when misconfigured higher', function () {
    config([
        'privacy.railgun.engine.rpc_upstream'  => ['polygon' => 'https://upstream.example/KEY'],
        'privacy.railgun.engine.rpc_proxy_ttl' => 99999, // operator misconfig
    ]);

    $data = $this->getJson('/api/v1/privacy/engine-config')->json('data');
    $provider = collect($data['networks'])->firstWhere('key', 'polygon')['fallback_provider_config']['providers'][0]['provider'];

    parse_str((string) parse_url($provider, PHP_URL_QUERY), $q);
    $expires = (int) ($q['expires'] ?? 0);
    $secondsValid = $expires - (int) now()->timestamp;

    expect($secondsValid)->toBeLessThanOrEqual(901);  // hard cap 900s, not 99999
    expect($secondsValid)->toBeGreaterThan(800);
});

it('requires authentication', function () {
    app('auth')->forgetGuards();

    $this->getJson('/api/v1/privacy/engine-config')->assertUnauthorized();
});
