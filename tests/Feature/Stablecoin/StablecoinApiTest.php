<?php

declare(strict_types=1);

namespace Tests\Feature\Stablecoin;

use App\Models\User;
use App\Models\Stablecoin;
use App\Domain\Asset\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesStablecoins;

class StablecoinApiTest extends TestCase
{
    use RefreshDatabase, CreatesStablecoins;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a user and authenticate
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        
        // Ensure required assets exist
        $this->ensureAssetsExist();
    }

    /** @test */
    public function it_can_list_stablecoins()
    {
        $this->createStablecoinWithAsset([
            'code' => 'FUSD',
            'name' => 'FinAegis USD',
            'symbol' => 'FUSD',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.5,
            'min_collateral_ratio' => 1.2,
            'liquidation_penalty' => 0.1,
            'total_supply' => 0,
            'max_supply' => 10000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);

        $this->createStablecoinWithAsset([
            'code' => 'FEUR',
            'name' => 'FinAegis EUR',
            'symbol' => 'FEUR',
            'peg_asset_code' => 'EUR',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.6,
            'min_collateral_ratio' => 1.3,
            'liquidation_penalty' => 0.12,
            'total_supply' => 0,
            'max_supply' => 5000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.006,
            'burn_fee' => 0.004,
            'precision' => 2,
            'is_active' => false,
            'minting_enabled' => false,
            'burning_enabled' => false,
        ]);

        $response = $this->getJson('/api/v2/stablecoins');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'code',
                        'name',
                        'symbol',
                        'peg_asset_code',
                        'stability_mechanism',
                        'collateral_ratio',
                        'is_active',
                    ]
                ]
            ]);

        // Test filtering
        $response = $this->getJson('/api/v2/stablecoins?active_only=true');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'FUSD');
    }

    /** @test */
    public function it_can_get_stablecoin_details()
    {
        $stablecoin = $this->createStablecoinWithAsset([
            'code' => 'FUSD',
            'name' => 'FinAegis USD',
            'symbol' => 'FUSD',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.5,
            'min_collateral_ratio' => 1.2,
            'liquidation_penalty' => 0.1,
            'total_supply' => 100000,
            'max_supply' => 10000000,
            'total_collateral_value' => 150000,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);

        $response = $this->getJson("/api/v2/stablecoins/{$stablecoin->code}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'code',
                    'name',
                    'symbol',
                    'peg_asset_code',
                    'stability_mechanism',
                    'collateral_ratio',
                    'min_collateral_ratio',
                    'total_supply',
                    'max_supply',
                    'total_collateral_value',
                    'global_collateralization_ratio',
                    'is_adequately_collateralized',
                    'active_positions_count',
                    'at_risk_positions_count',
                ]
            ])
            ->assertJsonPath('data.code', 'FUSD')
            ->assertJsonPath('data.global_collateralization_ratio', 1.5)
            ->assertJsonPath('data.is_adequately_collateralized', true);
    }

    /** @test */
    public function it_can_create_a_stablecoin()
    {
        $data = [
            'code' => 'FJPY',
            'name' => 'FinAegis JPY',
            'symbol' => 'FJPY',
            'peg_asset_code' => 'USD', // Using USD since JPY asset doesn't exist in test
            'peg_ratio' => 100.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.4,
            'min_collateral_ratio' => 1.15,
            'liquidation_penalty' => 0.09,
            'max_supply' => 2000000,
            'mint_fee' => 0.004,
            'burn_fee' => 0.002,
            'precision' => 0,
        ];

        $response = $this->postJson('/api/v2/stablecoins', $data);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'FJPY')
            ->assertJsonPath('data.name', 'FinAegis JPY')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.minting_enabled', true);

        $this->assertDatabaseHas('stablecoins', [
            'code' => 'FJPY',
            'name' => 'FinAegis JPY',
        ]);
    }

    /** @test */
    public function it_validates_stablecoin_creation()
    {
        $data = [
            'code' => 'FTEST',
            'name' => 'Test Stablecoin',
            'symbol' => 'FTEST',
            'peg_asset_code' => 'INVALID',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'invalid_mechanism',
            'collateral_ratio' => 0.5, // Too low
            'min_collateral_ratio' => 0.8,
            'liquidation_penalty' => 2.0, // Too high
            'mint_fee' => 1.5, // Too high
            'burn_fee' => -0.1, // Negative
            'precision' => 20, // Too high
        ];

        $response = $this->postJson('/api/v2/stablecoins', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'peg_asset_code',
                'stability_mechanism',
                'collateral_ratio',
                'liquidation_penalty',
                'mint_fee',
                'burn_fee',
                'precision',
            ]);
    }

    /** @test */
    public function it_can_update_a_stablecoin()
    {
        $stablecoin = $this->createStablecoinWithAsset([
            'code' => 'FUSD',
            'name' => 'FinAegis USD',
            'symbol' => 'FUSD',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.5,
            'min_collateral_ratio' => 1.2,
            'liquidation_penalty' => 0.1,
            'total_supply' => 0,
            'max_supply' => 10000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);

        $updateData = [
            'collateral_ratio' => 1.6,
            'mint_fee' => 0.004,
            'burn_fee' => 0.002,
        ];

        $response = $this->putJson("/api/v2/stablecoins/{$stablecoin->code}", $updateData);

        $response->assertOk()
            ->assertJsonPath('data.collateral_ratio', '1.6000')
            ->assertJsonPath('data.mint_fee', '0.004000')
            ->assertJsonPath('data.burn_fee', '0.002000');

        $this->assertDatabaseHas('stablecoins', [
            'code' => 'FUSD',
            'collateral_ratio' => 1.6,
            'mint_fee' => 0.004,
            'burn_fee' => 0.002,
        ]);
    }

    /** @test */
    public function it_can_get_system_metrics()
    {
        $this->createStablecoinWithAsset([
            'code' => 'FUSD',
            'name' => 'FinAegis USD',
            'symbol' => 'FUSD',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.5,
            'min_collateral_ratio' => 1.2,
            'liquidation_penalty' => 0.1,
            'total_supply' => 100000,
            'max_supply' => 10000000,
            'total_collateral_value' => 150000,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);

        $response = $this->getJson('/api/v2/stablecoins/metrics');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'FUSD' => [
                        'stablecoin_code',
                        'total_supply',
                        'total_collateral_value',
                        'global_ratio',
                        'target_ratio',
                        'min_ratio',
                        'active_positions',
                        'at_risk_positions',
                        'is_healthy',
                        'collateral_distribution',
                    ]
                ]
            ])
            ->assertJsonPath('data.FUSD.total_supply', 100000);
    }

    /** @test */
    public function it_can_check_system_health()
    {
        $this->createStablecoinWithAsset([
            'code' => 'FUSD',
            'name' => 'FinAegis USD',
            'symbol' => 'FUSD',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.5,
            'min_collateral_ratio' => 1.2,
            'liquidation_penalty' => 0.1,
            'total_supply' => 100000,
            'max_supply' => 10000000,
            'total_collateral_value' => 100000, // Under-collateralized
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);

        $response = $this->getJson('/api/v2/stablecoins/health');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'overall_status',
                    'stablecoin_status' => [
                        '*' => [
                            'code',
                            'is_healthy',
                            'global_ratio',
                            'at_risk_positions',
                            'status',
                        ]
                    ],
                    'emergency_actions',
                ]
            ])
            ->assertJsonPath('data.overall_status', 'critical')
            ->assertJsonPath('data.stablecoin_status.0.status', 'critical');
    }

    /** @test */
    public function it_can_deactivate_a_stablecoin()
    {
        $stablecoin = $this->createStablecoinWithAsset([
            'code' => 'FUSD',
            'name' => 'FinAegis USD',
            'symbol' => 'FUSD',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.5,
            'min_collateral_ratio' => 1.2,
            'liquidation_penalty' => 0.1,
            'total_supply' => 0,
            'max_supply' => 10000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);

        $response = $this->postJson("/api/v2/stablecoins/{$stablecoin->code}/deactivate");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.minting_enabled', false)
            ->assertJsonPath('data.burning_enabled', false);

        $this->assertDatabaseHas('stablecoins', [
            'code' => 'FUSD',
            'is_active' => false,
            'minting_enabled' => false,
            'burning_enabled' => false,
        ]);
    }

    /** @test */
    public function it_can_reactivate_a_stablecoin()
    {
        $stablecoin = $this->createStablecoinWithAsset([
            'code' => 'FUSD',
            'name' => 'FinAegis USD',
            'symbol' => 'FUSD',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.5,
            'min_collateral_ratio' => 1.2,
            'liquidation_penalty' => 0.1,
            'total_supply' => 0,
            'max_supply' => 10000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => false,
            'minting_enabled' => false,
            'burning_enabled' => false,
        ]);

        $response = $this->postJson("/api/v2/stablecoins/{$stablecoin->code}/reactivate");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.minting_enabled', true)
            ->assertJsonPath('data.burning_enabled', true);

        $this->assertDatabaseHas('stablecoins', [
            'code' => 'FUSD',
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
    }
}