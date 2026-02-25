<?php

declare(strict_types=1);

use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Domain\X402\Models\X402Payment;
use App\Domain\X402\Models\X402SpendingLimit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------

function createAuthenticatedUser(): User
{
    $user = User::factory()->withPersonalTeam()->create();
    Sanctum::actingAs($user);

    return $user;
}

function teamId(User $user): int
{
    return (int) $user->currentTeam?->id;
}

/**
 * @param array<string, mixed> $overrides
 */
function createEndpoint(User $user, array $overrides = []): X402MonetizedEndpoint
{
    return X402MonetizedEndpoint::create(array_merge([
        'method'      => 'GET',
        'path'        => 'api/v1/test/resource',
        'price'       => '0.01',
        'network'     => 'eip155:8453',
        'asset'       => 'USDC',
        'scheme'      => 'exact',
        'description' => 'Test endpoint',
        'mime_type'   => 'application/json',
        'is_active'   => true,
        'team_id'     => teamId($user),
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function createPayment(User $user, array $overrides = []): X402Payment
{
    return X402Payment::create(array_merge([
        'payer_address'   => '0x1234567890abcdef1234567890abcdef12345678',
        'pay_to_address'  => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
        'amount'          => '1000000',
        'network'         => 'eip155:8453',
        'asset'           => 'USDC',
        'scheme'          => 'exact',
        'status'          => 'pending',
        'endpoint_method' => 'GET',
        'endpoint_path'   => 'api/v1/test/resource',
        'team_id'         => teamId($user),
    ], $overrides));
}

/**
 * @param array<string, mixed> $overrides
 */
function createSpendingLimit(User $user, array $overrides = []): X402SpendingLimit
{
    return X402SpendingLimit::create(array_merge([
        'agent_id'              => 'agent-' . uniqid(),
        'agent_type'            => 'ai_agent',
        'daily_limit'           => '10000000',
        'spent_today'           => '0',
        'per_transaction_limit' => '1000000',
        'auto_pay_enabled'      => false,
        'limit_resets_at'       => now()->addDay(),
        'team_id'               => teamId($user),
    ], $overrides));
}

// ================================================================
// PUBLIC ENDPOINTS
// ================================================================

// ----------------------------------------------------------------
// GET /api/v1/x402/status
// ----------------------------------------------------------------

test('GET /api/v1/x402/status returns protocol status', function () {
    config(['x402.enabled' => true]);
    config(['x402.version' => 2]);
    config(['x402.server.default_network' => 'eip155:8453']);
    config(['x402.client.enabled' => false]);

    $response = $this->getJson('/api/v1/x402/status');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'enabled',
                'version',
                'protocol',
                'default_network',
                'supported_schemes',
                'client_enabled',
            ],
        ])
        ->assertJsonPath('data.protocol', 'x402')
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.supported_schemes', ['exact']);
});

test('GET /api/v1/x402/status works without authentication', function () {
    $response = $this->getJson('/api/v1/x402/status');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['enabled', 'version', 'protocol']]);
});

// ----------------------------------------------------------------
// GET /api/v1/x402/supported
// ----------------------------------------------------------------

test('GET /api/v1/x402/supported returns supported networks and assets', function () {
    $response = $this->getJson('/api/v1/x402/supported');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'networks' => [
                    '*' => [
                        'id',
                        'name',
                        'testnet',
                        'chain_id',
                        'usdc_address',
                        'usdc_decimals',
                        'explorer_url',
                    ],
                ],
                'contracts' => [
                    'permit2',
                    'exact_permit2_proxy',
                ],
                'supported_schemes',
                'supported_assets',
            ],
        ])
        ->assertJsonPath('data.supported_schemes', ['exact'])
        ->assertJsonPath('data.supported_assets', ['USDC']);
});

test('GET /api/v1/x402/supported includes all known networks', function () {
    $response = $this->getJson('/api/v1/x402/supported');

    $response->assertOk();

    $networks = $response->json('data.networks');
    $networkIds = collect($networks)->pluck('id')->toArray();

    expect($networkIds)->toContain('eip155:8453')   // Base Mainnet
        ->toContain('eip155:84532')                   // Base Sepolia
        ->toContain('eip155:1')                       // Ethereum Mainnet
        ->toContain('eip155:11155111');                // Sepolia
});

test('GET /api/v1/x402/supported works without authentication', function () {
    $response = $this->getJson('/api/v1/x402/supported');

    $response->assertOk();
});

// ================================================================
// AUTHENTICATED ENDPOINTS
// ================================================================

// ----------------------------------------------------------------
// Authentication Enforcement
// ----------------------------------------------------------------

/** @phpstan-ignore method.notFound */
test('authenticated endpoints return 401 without auth', function (string $method, string $uri) {
    $response = $this->json($method, $uri);
    $response->assertUnauthorized();
})->with([
    ['GET', '/api/v1/x402/endpoints'],
    ['POST', '/api/v1/x402/endpoints'],
    ['GET', '/api/v1/x402/endpoints/nonexistent-id'],
    ['PUT', '/api/v1/x402/endpoints/nonexistent-id'],
    ['DELETE', '/api/v1/x402/endpoints/nonexistent-id'],
    ['GET', '/api/v1/x402/payments'],
    ['GET', '/api/v1/x402/payments/stats'],
    ['GET', '/api/v1/x402/spending-limits'],
]);

// ================================================================
// ENDPOINT MANAGEMENT (CRUD)
// ================================================================

// ----------------------------------------------------------------
// GET /api/v1/x402/endpoints (index)
// ----------------------------------------------------------------

test('GET /api/v1/x402/endpoints returns paginated endpoints for current team', function () {
    $user = createAuthenticatedUser();
    createEndpoint($user, ['path' => 'api/v1/test/one']);
    createEndpoint($user, ['method' => 'POST', 'path' => 'api/v1/test/two']);

    $response = $this->getJson('/api/v1/x402/endpoints');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'method',
                    'path',
                    'price',
                    'network',
                    'asset',
                    'scheme',
                    'description',
                    'mimeType',
                    'isActive',
                    'createdAt',
                    'updatedAt',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ])
        ->assertJsonPath('meta.total', 2);
});

test('GET /api/v1/x402/endpoints does not return endpoints from other teams', function () {
    $user = createAuthenticatedUser();
    createEndpoint($user, ['path' => 'api/v1/my/endpoint']);

    // Create an endpoint for a different team
    $otherUser = User::factory()->withPersonalTeam()->create();
    createEndpoint($otherUser, ['path' => 'api/v1/other/endpoint']);

    $response = $this->getJson('/api/v1/x402/endpoints');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.path', 'api/v1/my/endpoint');
});

test('GET /api/v1/x402/endpoints filters by active status', function () {
    $user = createAuthenticatedUser();
    createEndpoint($user, ['path' => 'api/v1/test/active', 'is_active' => true]);
    createEndpoint($user, ['method' => 'POST', 'path' => 'api/v1/test/inactive', 'is_active' => false]);

    $response = $this->getJson('/api/v1/x402/endpoints?active=true');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.isActive', true);
});

test('GET /api/v1/x402/endpoints filters by network', function () {
    $user = createAuthenticatedUser();
    createEndpoint($user, ['path' => 'api/v1/test/base', 'network' => 'eip155:8453']);
    createEndpoint($user, ['method' => 'POST', 'path' => 'api/v1/test/eth', 'network' => 'eip155:1']);

    $response = $this->getJson('/api/v1/x402/endpoints?network=eip155:8453');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.network', 'eip155:8453');
});

test('GET /api/v1/x402/endpoints respects per_page parameter', function () {
    $user = createAuthenticatedUser();
    for ($i = 1; $i <= 5; $i++) {
        createEndpoint($user, ['path' => "api/v1/test/ep{$i}", 'method' => $i <= 3 ? 'GET' : 'POST']);
    }

    $response = $this->getJson('/api/v1/x402/endpoints?per_page=2');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonCount(2, 'data');
});

// ----------------------------------------------------------------
// POST /api/v1/x402/endpoints (store)
// ----------------------------------------------------------------

test('POST /api/v1/x402/endpoints creates a monetized endpoint', function () {
    $user = createAuthenticatedUser();

    $response = $this->postJson('/api/v1/x402/endpoints', [
        'method'      => 'GET',
        'path'        => 'api/v1/premium/data',
        'price'       => '0.005',
        'network'     => 'eip155:8453',
        'description' => 'Premium data endpoint',
        'is_active'   => true,
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'method',
                'path',
                'price',
                'network',
                'asset',
                'scheme',
                'description',
                'mimeType',
                'isActive',
                'createdAt',
                'updatedAt',
            ],
            'message',
        ])
        ->assertJsonPath('data.method', 'GET')
        ->assertJsonPath('data.path', 'api/v1/premium/data')
        ->assertJsonPath('data.price', '0.005')
        ->assertJsonPath('data.network', 'eip155:8453')
        ->assertJsonPath('data.description', 'Premium data endpoint')
        ->assertJsonPath('data.isActive', true)
        ->assertJsonPath('message', 'Endpoint monetized successfully.');

    $this->assertDatabaseHas('x402_monetized_endpoints', [
        'method'  => 'GET',
        'path'    => 'api/v1/premium/data',
        'price'   => '0.005',
        'team_id' => teamId($user),
    ]);
});

test('POST /api/v1/x402/endpoints assigns default network when omitted', function () {
    createAuthenticatedUser();
    config(['x402.server.default_network' => 'eip155:84532']);

    $response = $this->postJson('/api/v1/x402/endpoints', [
        'method' => 'POST',
        'path'   => 'api/v1/test/default-network',
        'price'  => '0.01',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.network', 'eip155:84532');
});

test('POST /api/v1/x402/endpoints returns 422 for missing required fields', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/x402/endpoints', []);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors'])
        ->assertJsonValidationErrors(['method', 'path', 'price']);
});

test('POST /api/v1/x402/endpoints returns 422 for invalid method', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/x402/endpoints', [
        'method' => 'INVALID',
        'path'   => 'api/v1/test/invalid',
        'price'  => '0.01',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['method']);
});

test('POST /api/v1/x402/endpoints returns 422 for invalid price format', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/x402/endpoints', [
        'method' => 'GET',
        'path'   => 'api/v1/test/bad-price',
        'price'  => 'free',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['price']);
});

test('POST /api/v1/x402/endpoints returns 422 for invalid network format', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/x402/endpoints', [
        'method'  => 'GET',
        'path'    => 'api/v1/test/bad-network',
        'price'   => '0.01',
        'network' => 'invalid-network',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['network']);
});

// ----------------------------------------------------------------
// GET /api/v1/x402/endpoints/{id} (show)
// ----------------------------------------------------------------

test('GET /api/v1/x402/endpoints/{id} returns endpoint details', function () {
    $user = createAuthenticatedUser();
    $endpoint = createEndpoint($user);

    $response = $this->getJson("/api/v1/x402/endpoints/{$endpoint->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'method',
                'path',
                'price',
                'network',
                'asset',
                'scheme',
                'description',
                'mimeType',
                'isActive',
                'createdAt',
                'updatedAt',
            ],
        ])
        ->assertJsonPath('data.id', $endpoint->id)
        ->assertJsonPath('data.method', 'GET')
        ->assertJsonPath('data.path', 'api/v1/test/resource');
});

test('GET /api/v1/x402/endpoints/{id} returns 404 for non-existent endpoint', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/x402/endpoints/00000000-0000-0000-0000-000000000000');

    $response->assertNotFound();
});

test('GET /api/v1/x402/endpoints/{id} returns 404 for endpoint from different team', function () {
    createAuthenticatedUser();
    $otherUser = User::factory()->withPersonalTeam()->create();
    $otherEndpoint = createEndpoint($otherUser, ['path' => 'api/v1/other/resource']);

    $response = $this->getJson("/api/v1/x402/endpoints/{$otherEndpoint->id}");

    $response->assertNotFound();
});

// ----------------------------------------------------------------
// PUT /api/v1/x402/endpoints/{id} (update)
// ----------------------------------------------------------------

test('PUT /api/v1/x402/endpoints/{id} updates endpoint fields', function () {
    $user = createAuthenticatedUser();
    $endpoint = createEndpoint($user);

    $response = $this->putJson("/api/v1/x402/endpoints/{$endpoint->id}", [
        'price'       => '0.05',
        'description' => 'Updated description',
        'is_active'   => false,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'method', 'path', 'price', 'isActive'],
            'message',
        ])
        ->assertJsonPath('data.price', '0.05')
        ->assertJsonPath('data.description', 'Updated description')
        ->assertJsonPath('data.isActive', false)
        ->assertJsonPath('message', 'Endpoint updated successfully.');
});

test('PUT /api/v1/x402/endpoints/{id} returns 404 for non-existent endpoint', function () {
    createAuthenticatedUser();

    $response = $this->putJson('/api/v1/x402/endpoints/00000000-0000-0000-0000-000000000000', [
        'price' => '0.05',
    ]);

    $response->assertNotFound();
});

test('PUT /api/v1/x402/endpoints/{id} returns 404 for endpoint from different team', function () {
    createAuthenticatedUser();
    $otherUser = User::factory()->withPersonalTeam()->create();
    $otherEndpoint = createEndpoint($otherUser, ['path' => 'api/v1/other/update']);

    $response = $this->putJson("/api/v1/x402/endpoints/{$otherEndpoint->id}", [
        'price' => '0.05',
    ]);

    $response->assertNotFound();
});

test('PUT /api/v1/x402/endpoints/{id} returns 422 for invalid price format', function () {
    $user = createAuthenticatedUser();
    $endpoint = createEndpoint($user);

    $response = $this->putJson("/api/v1/x402/endpoints/{$endpoint->id}", [
        'price' => 'not-a-number',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['price']);
});

test('PUT /api/v1/x402/endpoints/{id} validates optional fields', function () {
    $user = createAuthenticatedUser();
    $endpoint = createEndpoint($user);

    $response = $this->putJson("/api/v1/x402/endpoints/{$endpoint->id}", [
        'scheme' => 'invalid_scheme',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['scheme']);
});

// ----------------------------------------------------------------
// DELETE /api/v1/x402/endpoints/{id} (destroy)
// ----------------------------------------------------------------

test('DELETE /api/v1/x402/endpoints/{id} removes endpoint', function () {
    $user = createAuthenticatedUser();
    $endpoint = createEndpoint($user);

    $response = $this->deleteJson("/api/v1/x402/endpoints/{$endpoint->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('x402_monetized_endpoints', [
        'id' => $endpoint->id,
    ]);
});

test('DELETE /api/v1/x402/endpoints/{id} returns 404 for non-existent endpoint', function () {
    createAuthenticatedUser();

    $response = $this->deleteJson('/api/v1/x402/endpoints/00000000-0000-0000-0000-000000000000');

    $response->assertNotFound();
});

test('DELETE /api/v1/x402/endpoints/{id} returns 404 for endpoint from different team', function () {
    createAuthenticatedUser();
    $otherUser = User::factory()->withPersonalTeam()->create();
    $otherEndpoint = createEndpoint($otherUser, ['path' => 'api/v1/other/delete']);

    $response = $this->deleteJson("/api/v1/x402/endpoints/{$otherEndpoint->id}");

    $response->assertNotFound();
});

// ================================================================
// PAYMENTS
// ================================================================

// ----------------------------------------------------------------
// GET /api/v1/x402/payments (index)
// ----------------------------------------------------------------

test('GET /api/v1/x402/payments returns paginated payments for current team', function () {
    $user = createAuthenticatedUser();
    createPayment($user, ['status' => 'settled', 'settled_at' => now()]);
    createPayment($user, ['status' => 'pending', 'payer_address' => '0xaabbccddaabbccddaabbccddaabbccddaabbccdd']);

    $response = $this->getJson('/api/v1/x402/payments');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'payerAddress',
                    'payToAddress',
                    'amount',
                    'amountUsd',
                    'network',
                    'asset',
                    'scheme',
                    'status',
                    'transactionHash',
                    'endpoint',
                    'error',
                    'verifiedAt',
                    'settledAt',
                    'createdAt',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ])
        ->assertJsonPath('meta.total', 2);
});

test('GET /api/v1/x402/payments does not return payments from other teams', function () {
    $user = createAuthenticatedUser();
    createPayment($user);

    $otherUser = User::factory()->withPersonalTeam()->create();
    createPayment($otherUser, ['payer_address' => '0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef']);

    $response = $this->getJson('/api/v1/x402/payments');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('GET /api/v1/x402/payments filters by status', function () {
    $user = createAuthenticatedUser();
    createPayment($user, ['status' => 'settled', 'settled_at' => now()]);
    createPayment($user, ['status' => 'pending', 'payer_address' => '0xaabbccddaabbccddaabbccddaabbccddaabbccdd']);

    $response = $this->getJson('/api/v1/x402/payments?status=settled');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', 'settled');
});

test('GET /api/v1/x402/payments returns 422 for invalid status', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/x402/payments?status=bogus');

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['status']]);
});

test('GET /api/v1/x402/payments filters by network', function () {
    $user = createAuthenticatedUser();
    createPayment($user, ['network' => 'eip155:8453']);
    createPayment($user, ['network' => 'eip155:1', 'payer_address' => '0xaabbccddaabbccddaabbccddaabbccddaabbccdd']);

    $response = $this->getJson('/api/v1/x402/payments?network=eip155:8453');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.network', 'eip155:8453');
});

test('GET /api/v1/x402/payments filters by payer_address', function () {
    $user = createAuthenticatedUser();
    createPayment($user, ['payer_address' => '0x1111111111111111111111111111111111111111']);
    createPayment($user, ['payer_address' => '0x2222222222222222222222222222222222222222']);

    $response = $this->getJson('/api/v1/x402/payments?payer_address=0x1111111111111111111111111111111111111111');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.payerAddress', '0x1111111111111111111111111111111111111111');
});

test('GET /api/v1/x402/payments respects per_page parameter', function () {
    $user = createAuthenticatedUser();
    for ($i = 0; $i < 5; $i++) {
        createPayment($user, ['payer_address' => '0x' . str_pad((string) ($i + 1), 40, '0', STR_PAD_LEFT)]);
    }

    $response = $this->getJson('/api/v1/x402/payments?per_page=3');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 3)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonCount(3, 'data');
});

// ----------------------------------------------------------------
// GET /api/v1/x402/payments/stats
// ----------------------------------------------------------------

test('GET /api/v1/x402/payments/stats returns payment statistics', function () {
    $user = createAuthenticatedUser();

    // Create some payments within the day
    createPayment($user, [
        'status'     => 'settled',
        'amount'     => '1000000',
        'settled_at' => now(),
    ]);
    createPayment($user, [
        'status'        => 'settled',
        'amount'        => '2000000',
        'payer_address' => '0xaabbccddaabbccddaabbccddaabbccddaabbccdd',
        'settled_at'    => now(),
    ]);
    createPayment($user, [
        'status'        => 'failed',
        'amount'        => '500000',
        'payer_address' => '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        'error_reason'  => 'insufficient_funds',
        'error_message' => 'Insufficient USDC balance',
    ]);

    $response = $this->getJson('/api/v1/x402/payments/stats');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'period',
                'total_payments',
                'total_settled',
                'total_failed',
                'total_volume_atomic',
                'total_volume_usd',
                'unique_payers',
            ],
        ])
        ->assertJsonPath('data.period', 'day')
        ->assertJsonPath('data.total_payments', 3)
        ->assertJsonPath('data.total_settled', 2)
        ->assertJsonPath('data.total_failed', 1);
});

test('GET /api/v1/x402/payments/stats accepts period parameter', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/x402/payments/stats?period=week');

    $response->assertOk()
        ->assertJsonPath('data.period', 'week');
});

test('GET /api/v1/x402/payments/stats accepts period aliases', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/x402/payments/stats?period=7d');

    $response->assertOk()
        ->assertJsonPath('data.period', 'week');
});

test('GET /api/v1/x402/payments/stats returns 422 for invalid period', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/x402/payments/stats?period=invalid');

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['period']]);
});

test('GET /api/v1/x402/payments/stats returns zeros when no payments exist', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/x402/payments/stats');

    $response->assertOk()
        ->assertJsonPath('data.total_payments', 0)
        ->assertJsonPath('data.total_settled', 0)
        ->assertJsonPath('data.total_failed', 0)
        ->assertJsonPath('data.unique_payers', 0);
});

// ================================================================
// SPENDING LIMITS
// ================================================================

// ----------------------------------------------------------------
// GET /api/v1/x402/spending-limits (index)
// ----------------------------------------------------------------

test('GET /api/v1/x402/spending-limits returns paginated spending limits', function () {
    $user = createAuthenticatedUser();
    createSpendingLimit($user, ['agent_id' => 'agent-alpha']);
    createSpendingLimit($user, ['agent_id' => 'agent-beta']);

    $response = $this->getJson('/api/v1/x402/spending-limits');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'agentId',
                    'agentType',
                    'dailyLimit',
                    'spentToday',
                    'perTransactionLimit',
                    'autoPayEnabled',
                    'remainingBudget',
                    'spentPercentage',
                    'limitResetsAt',
                    'createdAt',
                    'updatedAt',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ])
        ->assertJsonPath('meta.total', 2);
});

test('GET /api/v1/x402/spending-limits does not return limits from other teams', function () {
    $user = createAuthenticatedUser();
    createSpendingLimit($user, ['agent_id' => 'my-agent']);

    $otherUser = User::factory()->withPersonalTeam()->create();
    createSpendingLimit($otherUser, ['agent_id' => 'other-agent']);

    $response = $this->getJson('/api/v1/x402/spending-limits');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('GET /api/v1/x402/spending-limits respects per_page parameter', function () {
    $user = createAuthenticatedUser();
    for ($i = 1; $i <= 5; $i++) {
        createSpendingLimit($user, ['agent_id' => "agent-{$i}"]);
    }

    $response = $this->getJson('/api/v1/x402/spending-limits?per_page=2');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonCount(2, 'data');
});

// ----------------------------------------------------------------
// POST /api/v1/x402/spending-limits (store - create)
// ----------------------------------------------------------------

test('POST /api/v1/x402/spending-limits creates a new spending limit', function () {
    $user = createAuthenticatedUser();

    $response = $this->postJson('/api/v1/x402/spending-limits', [
        'agent_id'              => 'new-ai-agent',
        'agent_type'            => 'ai_agent',
        'daily_limit'           => '50000000',
        'per_transaction_limit' => '5000000',
        'auto_pay_enabled'      => true,
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'agentId',
                'agentType',
                'dailyLimit',
                'spentToday',
                'perTransactionLimit',
                'autoPayEnabled',
                'remainingBudget',
                'spentPercentage',
                'limitResetsAt',
                'createdAt',
                'updatedAt',
            ],
            'message',
        ])
        ->assertJsonPath('data.agentId', 'new-ai-agent')
        ->assertJsonPath('data.agentType', 'ai_agent')
        ->assertJsonPath('data.dailyLimit', '50000000')
        ->assertJsonPath('data.perTransactionLimit', '5000000')
        ->assertJsonPath('data.autoPayEnabled', true);

    $this->assertDatabaseHas('x402_spending_limits', [
        'agent_id'    => 'new-ai-agent',
        'daily_limit' => '50000000',
        'team_id'     => teamId($user),
    ]);
});

test('POST /api/v1/x402/spending-limits updates existing limit for same agent', function () {
    $user = createAuthenticatedUser();
    createSpendingLimit($user, [
        'agent_id'    => 'existing-agent',
        'daily_limit' => '10000000',
    ]);

    $response = $this->postJson('/api/v1/x402/spending-limits', [
        'agent_id'    => 'existing-agent',
        'daily_limit' => '20000000',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.agentId', 'existing-agent')
        ->assertJsonPath('data.dailyLimit', '20000000');

    // Should still be only one record
    $this->assertDatabaseCount('x402_spending_limits', 1);
});

test('POST /api/v1/x402/spending-limits returns 422 for missing required fields', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/x402/spending-limits', []);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors'])
        ->assertJsonValidationErrors(['agent_id', 'daily_limit']);
});

test('POST /api/v1/x402/spending-limits returns 422 for non-integer daily limit', function () {
    createAuthenticatedUser();

    $response = $this->postJson('/api/v1/x402/spending-limits', [
        'agent_id'    => 'test-agent',
        'daily_limit' => 'not-a-number',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['daily_limit']);
});

// ----------------------------------------------------------------
// GET /api/v1/x402/spending-limits/{agentId} (show)
// ----------------------------------------------------------------

test('GET /api/v1/x402/spending-limits/{agentId} returns limit details', function () {
    $user = createAuthenticatedUser();
    $limit = createSpendingLimit($user, ['agent_id' => 'my-agent-show']);

    $response = $this->getJson('/api/v1/x402/spending-limits/my-agent-show');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'agentId',
                'agentType',
                'dailyLimit',
                'spentToday',
                'perTransactionLimit',
                'autoPayEnabled',
                'remainingBudget',
                'spentPercentage',
                'limitResetsAt',
                'createdAt',
                'updatedAt',
            ],
        ])
        ->assertJsonPath('data.id', $limit->id)
        ->assertJsonPath('data.agentId', 'my-agent-show');
});

test('GET /api/v1/x402/spending-limits/{agentId} returns 404 for non-existent agent', function () {
    createAuthenticatedUser();

    $response = $this->getJson('/api/v1/x402/spending-limits/does-not-exist');

    $response->assertNotFound();
});

test('GET /api/v1/x402/spending-limits/{agentId} returns 404 for agent from different team', function () {
    createAuthenticatedUser();
    $otherUser = User::factory()->withPersonalTeam()->create();
    createSpendingLimit($otherUser, ['agent_id' => 'other-team-agent']);

    $response = $this->getJson('/api/v1/x402/spending-limits/other-team-agent');

    $response->assertNotFound();
});

// ----------------------------------------------------------------
// PUT /api/v1/x402/spending-limits/{agentId} (update)
// ----------------------------------------------------------------

test('PUT /api/v1/x402/spending-limits/{agentId} updates spending limit', function () {
    $user = createAuthenticatedUser();
    createSpendingLimit($user, ['agent_id' => 'agent-update']);

    $response = $this->putJson('/api/v1/x402/spending-limits/agent-update', [
        'daily_limit'      => '99000000',
        'auto_pay_enabled' => true,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'agentId', 'dailyLimit', 'autoPayEnabled'],
            'message',
        ])
        ->assertJsonPath('data.dailyLimit', '99000000')
        ->assertJsonPath('data.autoPayEnabled', true)
        ->assertJsonPath('message', 'Spending limit updated.');
});

test('PUT /api/v1/x402/spending-limits/{agentId} returns 404 for non-existent agent', function () {
    createAuthenticatedUser();

    $response = $this->putJson('/api/v1/x402/spending-limits/does-not-exist', [
        'daily_limit' => '50000000',
    ]);

    $response->assertNotFound();
});

test('PUT /api/v1/x402/spending-limits/{agentId} returns 422 for invalid data', function () {
    $user = createAuthenticatedUser();
    createSpendingLimit($user, ['agent_id' => 'agent-validate']);

    $response = $this->putJson('/api/v1/x402/spending-limits/agent-validate', [
        'daily_limit' => 'not-numeric',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['daily_limit']);
});

// ----------------------------------------------------------------
// DELETE /api/v1/x402/spending-limits/{agentId} (destroy)
// ----------------------------------------------------------------

test('DELETE /api/v1/x402/spending-limits/{agentId} removes spending limit', function () {
    $user = createAuthenticatedUser();
    $limit = createSpendingLimit($user, ['agent_id' => 'agent-delete']);

    $response = $this->deleteJson('/api/v1/x402/spending-limits/agent-delete');

    $response->assertNoContent();

    $this->assertDatabaseMissing('x402_spending_limits', [
        'id' => $limit->id,
    ]);
});

test('DELETE /api/v1/x402/spending-limits/{agentId} returns 404 for non-existent agent', function () {
    createAuthenticatedUser();

    $response = $this->deleteJson('/api/v1/x402/spending-limits/nonexistent');

    $response->assertNotFound();
});

test('DELETE /api/v1/x402/spending-limits/{agentId} returns 404 for agent from different team', function () {
    createAuthenticatedUser();
    $otherUser = User::factory()->withPersonalTeam()->create();
    createSpendingLimit($otherUser, ['agent_id' => 'other-agent-delete']);

    $response = $this->deleteJson('/api/v1/x402/spending-limits/other-agent-delete');

    $response->assertNotFound();
});

// ================================================================
// FULL LIFECYCLE TESTS
// ================================================================

test('full endpoint CRUD lifecycle works end-to-end', function () {
    createAuthenticatedUser();

    // Create
    $createResponse = $this->postJson('/api/v1/x402/endpoints', [
        'method'      => 'POST',
        'path'        => 'api/v1/lifecycle/test',
        'price'       => '0.10',
        'description' => 'Lifecycle test endpoint',
    ]);
    $createResponse->assertCreated();
    $endpointId = $createResponse->json('data.id');

    // Read
    $showResponse = $this->getJson("/api/v1/x402/endpoints/{$endpointId}");
    $showResponse->assertOk()
        ->assertJsonPath('data.price', '0.10');

    // Update
    $updateResponse = $this->putJson("/api/v1/x402/endpoints/{$endpointId}", [
        'price'     => '0.25',
        'is_active' => false,
    ]);
    $updateResponse->assertOk()
        ->assertJsonPath('data.price', '0.25')
        ->assertJsonPath('data.isActive', false);

    // List
    $listResponse = $this->getJson('/api/v1/x402/endpoints');
    $listResponse->assertOk()
        ->assertJsonPath('meta.total', 1);

    // Delete
    $deleteResponse = $this->deleteJson("/api/v1/x402/endpoints/{$endpointId}");
    $deleteResponse->assertNoContent();

    // Verify deleted
    $this->getJson("/api/v1/x402/endpoints/{$endpointId}")
        ->assertNotFound();
});

test('full spending limit lifecycle works end-to-end', function () {
    createAuthenticatedUser();

    // Create
    $createResponse = $this->postJson('/api/v1/x402/spending-limits', [
        'agent_id'    => 'lifecycle-agent',
        'agent_type'  => 'ai_agent',
        'daily_limit' => '10000000',
    ]);
    $createResponse->assertCreated();

    // Read
    $showResponse = $this->getJson('/api/v1/x402/spending-limits/lifecycle-agent');
    $showResponse->assertOk()
        ->assertJsonPath('data.agentId', 'lifecycle-agent');

    // Update
    $updateResponse = $this->putJson('/api/v1/x402/spending-limits/lifecycle-agent', [
        'daily_limit'      => '50000000',
        'auto_pay_enabled' => true,
    ]);
    $updateResponse->assertOk()
        ->assertJsonPath('data.dailyLimit', '50000000')
        ->assertJsonPath('data.autoPayEnabled', true);

    // List
    $listResponse = $this->getJson('/api/v1/x402/spending-limits');
    $listResponse->assertOk()
        ->assertJsonPath('meta.total', 1);

    // Delete
    $deleteResponse = $this->deleteJson('/api/v1/x402/spending-limits/lifecycle-agent');
    $deleteResponse->assertNoContent();

    // Verify deleted
    $this->getJson('/api/v1/x402/spending-limits/lifecycle-agent')
        ->assertNotFound();
});
