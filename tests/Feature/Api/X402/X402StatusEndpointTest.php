<?php

declare(strict_types=1);

test('x402 status endpoint returns protocol info', function () {
    config(['x402.enabled' => true]);
    config(['x402.version' => 2]);

    $response = $this->getJson('/api/v1/x402/status');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'enabled',
            'version',
            'protocol',
            'default_network',
            'supported_schemes',
        ],
    ]);
    $response->assertJsonFragment(['protocol' => 'x402']);
});

test('x402 supported endpoint returns networks', function () {
    $response = $this->getJson('/api/v1/x402/supported');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'networks' => [
                '*' => ['id', 'name', 'testnet', 'chain_id', 'usdc_address', 'usdc_decimals'],
            ],
            'contracts',
            'supported_schemes',
            'supported_assets',
        ],
    ]);
});

test('x402 endpoints require authentication', function () {
    $response = $this->getJson('/api/v1/x402/endpoints');
    $response->assertUnauthorized();
});

test('x402 payments require authentication', function () {
    $response = $this->getJson('/api/v1/x402/payments');
    $response->assertUnauthorized();
});

test('x402 spending limits require authentication', function () {
    $response = $this->getJson('/api/v1/x402/spending-limits');
    $response->assertUnauthorized();
});
