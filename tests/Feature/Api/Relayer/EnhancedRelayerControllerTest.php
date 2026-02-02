<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Relayer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for enhanced Relayer API endpoints (v2.6.0).
 */
class EnhancedRelayerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['read', 'write']);
    }

    // ========================================================================
    // GET /api/v1/relayer/networks - Enhanced Response Tests
    // ========================================================================

    public function test_networks_returns_entrypoint_address(): void
    {
        $response = $this->getJson('/api/v1/relayer/networks');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'chain_id',
                        'name',
                        'entrypoint_address',
                    ],
                ],
            ]);

        // Verify entrypoint address format
        $networks = $response->json('data');
        foreach ($networks as $network) {
            $this->assertMatchesRegularExpression(
                '/^0x[a-fA-F0-9]{40}$/',
                $network['entrypoint_address']
            );
        }
    }

    public function test_networks_returns_factory_address(): void
    {
        $response = $this->getJson('/api/v1/relayer/networks');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['factory_address'],
                ],
            ]);
    }

    public function test_networks_returns_paymaster_address(): void
    {
        $response = $this->getJson('/api/v1/relayer/networks');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['paymaster_address'],
                ],
            ]);
    }

    public function test_networks_returns_current_gas_price(): void
    {
        $response = $this->getJson('/api/v1/relayer/networks');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['current_gas_price'],
                ],
            ]);

        // Verify gas prices are numeric strings
        $networks = $response->json('data');
        foreach ($networks as $network) {
            $this->assertIsString($network['current_gas_price']);
            $this->assertGreaterThanOrEqual(0, (float) $network['current_gas_price']);
        }
    }

    public function test_networks_returns_average_fee_usdc(): void
    {
        $response = $this->getJson('/api/v1/relayer/networks');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['average_fee_usdc'],
                ],
            ]);
    }

    public function test_networks_returns_congestion_level(): void
    {
        $response = $this->getJson('/api/v1/relayer/networks');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['congestion_level'],
                ],
            ]);

        // Verify congestion level is one of expected values
        $networks = $response->json('data');
        foreach ($networks as $network) {
            $this->assertContains(
                $network['congestion_level'],
                ['low', 'medium', 'high']
            );
        }
    }

    public function test_networks_includes_erc4337_entrypoint_v06(): void
    {
        $response = $this->getJson('/api/v1/relayer/networks');

        $response->assertOk();

        $networks = $response->json('data');

        // ERC-4337 EntryPoint v0.6 should be the default
        foreach ($networks as $network) {
            $this->assertEquals(
                '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789',
                $network['entrypoint_address'],
                "Network {$network['name']} should use EntryPoint v0.6"
            );
        }
    }

    // ========================================================================
    // POST /api/v1/relayer/sponsor - initCode Support Tests
    // ========================================================================

    public function test_sponsor_accepts_init_code_parameter(): void
    {
        $initCode = '0x' . str_repeat('ab', 20) . str_repeat('cd', 32);

        // Note: This will fail at the bundler level in test, but validates the parameter is accepted
        $response = $this->postJson('/api/v1/relayer/sponsor', [
            'user_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'call_data'    => '0x',
            'signature'    => '0x' . str_repeat('00', 65),
            'network'      => 'polygon',
            'fee_token'    => 'USDC',
            'init_code'    => $initCode,
        ]);

        // The endpoint should accept the parameter (even if operation fails)
        $this->assertNotEquals(422, $response->status());
    }

    public function test_sponsor_validates_init_code_format(): void
    {
        $response = $this->postJson('/api/v1/relayer/sponsor', [
            'user_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'call_data'    => '0x',
            'signature'    => '0x' . str_repeat('00', 65),
            'network'      => 'polygon',
            'init_code'    => 'invalid_not_hex',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['init_code']);
    }

    public function test_sponsor_allows_empty_init_code(): void
    {
        $response = $this->postJson('/api/v1/relayer/sponsor', [
            'user_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'call_data'    => '0x',
            'signature'    => '0x' . str_repeat('00', 65),
            'network'      => 'polygon',
            'init_code'    => '0x',
        ]);

        // Should be accepted (empty initCode means not a deployment)
        $this->assertNotEquals(422, $response->status());
    }

    public function test_sponsor_allows_null_init_code(): void
    {
        $response = $this->postJson('/api/v1/relayer/sponsor', [
            'user_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'call_data'    => '0x',
            'signature'    => '0x' . str_repeat('00', 65),
            'network'      => 'polygon',
            // No init_code parameter
        ]);

        $this->assertNotEquals(422, $response->status());
    }

    public function test_sponsor_without_init_code_is_not_deployment(): void
    {
        // This test verifies the flow but may fail at bundler level
        $response = $this->postJson('/api/v1/relayer/sponsor', [
            'user_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'call_data'    => '0xabcdef',
            'signature'    => '0x' . str_repeat('00', 65),
            'network'      => 'polygon',
        ]);

        // If successful, verify is_deployment is false
        if ($response->json('success')) {
            $response->assertJsonPath('data.is_deployment', false);
        }
    }
}
