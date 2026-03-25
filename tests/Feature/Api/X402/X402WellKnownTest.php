<?php

declare(strict_types=1);

test('x402 well-known endpoint returns discovery configuration', function () {
    config(['x402.enabled' => true]);
    config(['x402.version' => 2]);

    $response = $this->getJson('/api/.well-known/x402-configuration');

    $response->assertOk();
    $response->assertJsonStructure([
        'x402_version',
        'issuer',
        'spec_url',
        'default_network',
        'supported_networks' => [
            '*' => ['id', 'name', 'testnet'],
        ],
        'supported_assets',
        'supported_schemes',
        'pay_to',
        'facilitator' => ['url', 'self_hosted'],
        'endpoints'   => ['status', 'supported', 'endpoints', 'payments', 'spending_limits'],
        'contracts',
    ]);
    $response->assertJsonFragment(['x402_version' => 2]);
    $response->assertJsonFragment(['spec_url' => 'https://x402.org']);
});

test('x402 well-known endpoint includes all networks', function () {
    $response = $this->getJson('/api/.well-known/x402-configuration');

    $response->assertOk();

    $networks = $response->json('supported_networks');
    $networkIds = array_column($networks, 'id');

    expect($networkIds)->toContain('eip155:8453');
    expect($networkIds)->toContain('solana:mainnet');
    expect($networks)->toHaveCount(8);
});

test('x402 well-known endpoint includes contracts', function () {
    $response = $this->getJson('/api/.well-known/x402-configuration');

    $response->assertOk();
    $response->assertJsonFragment([
        'permit2' => '0x000000000022D473030F116dDEE9F6B43aC78BA3',
    ]);
});

test('x402 well-known endpoint does not require authentication', function () {
    $response = $this->getJson('/api/.well-known/x402-configuration');

    $response->assertOk();
});
