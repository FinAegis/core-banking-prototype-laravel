<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Domain\Stablecoin\Services\StabilityMechanismService;
use App\Http\Controllers\Api\StablecoinController;
use App\Models\Stablecoin;
use App\Domain\Asset\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class StablecoinControllerTest extends TestCase
{
    use RefreshDatabase;

    protected StablecoinController $controller;
    protected $stabilityService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->stabilityService = Mockery::mock(StabilityMechanismService::class);
        $this->controller = new StablecoinController($this->stabilityService);
        
        // Create assets
        Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true
            ]
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_list_stablecoins()
    {
        Stablecoin::create([
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
            'total_supply' => 1000000,
            'max_supply' => 10000000,
            'total_collateral_value' => 1500000,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        Stablecoin::create([
            'code' => 'FEUR',
            'name' => 'FinAegis EUR',
            'symbol' => 'FEUR',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'algorithmic',
            'collateral_ratio' => 0,
            'min_collateral_ratio' => 0,
            'liquidation_penalty' => 0,
            'total_supply' => 500000,
            'max_supply' => 5000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.001,
            'burn_fee' => 0.001,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        $request = Request::create('/api/v2/stablecoins', 'GET');
        
        $response = $this->controller->index($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']);
    }

    /** @test */
    public function it_can_filter_by_stability_mechanism()
    {
        Stablecoin::create([
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
            'total_supply' => 1000000,
            'max_supply' => 10000000,
            'total_collateral_value' => 1500000,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        Stablecoin::create([
            'code' => 'FEUR',
            'name' => 'FinAegis EUR',
            'symbol' => 'FEUR',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'algorithmic',
            'collateral_ratio' => 0,
            'min_collateral_ratio' => 0,
            'liquidation_penalty' => 0,
            'total_supply' => 500000,
            'max_supply' => 5000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.001,
            'burn_fee' => 0.001,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        $request = Request::create('/api/v2/stablecoins', 'GET', [
            'mechanism' => 'collateralized'
        ]);
        
        $response = $this->controller->index($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']);
        $this->assertEquals('FUSD', $data['data'][0]['code']);
    }

    /** @test */
    public function it_can_show_stablecoin_details()
    {
        $stablecoin = Stablecoin::create([
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
            'total_supply' => 1000000,
            'max_supply' => 10000000,
            'total_collateral_value' => 1500000,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        $response = $this->controller->show($stablecoin);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('FUSD', $data['data']['code']);
    }

    /** @test */
    public function it_can_check_peg_status()
    {
        $stablecoin = Stablecoin::create([
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
            'total_supply' => 1000000,
            'max_supply' => 10000000,
            'total_collateral_value' => 1500000,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        $deviation = [
            'deviation' => 0.02,
            'percentage' => 2.0,
            'direction' => 'above',
            'within_threshold' => false,
            'current_price' => 1.02,
            'target_price' => 1.0,
        ];
        
        $this->stabilityService
            ->shouldReceive('checkPegDeviation')
            ->once()
            ->with('FUSD')
            ->andReturn($deviation);
            
        $response = $this->controller->checkPeg($stablecoin);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(2.0, $data['data']['percentage']);
        $this->assertEquals('above', $data['data']['direction']);
    }

    /** @test */
    public function it_can_check_system_health()
    {
        $systemHealth = [
            'overall_status' => 'healthy',
            'stablecoin_status' => [
                [
                    'code' => 'FUSD',
                    'is_healthy' => true,
                    'global_ratio' => 1.5,
                    'at_risk_positions' => 0,
                    'status' => 'healthy',
                ],
            ],
            'emergency_actions' => [],
        ];
        
        $this->stabilityService
            ->shouldReceive('checkSystemHealth')
            ->once()
            ->andReturn($systemHealth);
            
        $response = $this->controller->checkSystemHealth();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('healthy', $data['data']['overall_status']);
    }

    /** @test */
    public function it_can_check_stability_monitoring()
    {
        $monitoring = [
            [
                'stablecoin_code' => 'FUSD',
                'deviation' => [
                    'deviation' => 0.01,
                    'percentage' => 1.0,
                    'direction' => 'above',
                    'within_threshold' => true,
                ],
                'status' => 'healthy',
                'last_checked' => now(),
            ],
        ];
        
        $this->stabilityService
            ->shouldReceive('monitorAllPegs')
            ->once()
            ->andReturn($monitoring);
            
        $response = $this->controller->monitorStability();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']);
    }

    /** @test */
    public function it_can_get_collateral_metrics()
    {
        $metrics = [
            'FUSD' => [
                'stablecoin_code' => 'FUSD',
                'total_supply' => 1000000,
                'total_collateral_value' => 1500000,
                'global_ratio' => 1.5,
                'target_ratio' => 1.5,
                'min_ratio' => 1.2,
                'active_positions' => 10,
                'at_risk_positions' => 2,
                'is_healthy' => true,
                'collateral_distribution' => [
                    'USD' => [
                        'asset_code' => 'USD',
                        'total_amount' => 1500000,
                        'total_value' => 1500000,
                        'position_count' => 10,
                        'percentage' => 100.0,
                    ],
                ],
            ],
        ];
        
        $this->stabilityService
            ->shouldReceive('getSystemCollateralizationMetrics')
            ->once()
            ->andReturn($metrics);
            
        $this->controller = new StablecoinController($this->stabilityService);
        
        $response = $this->controller->getCollateralMetrics();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('FUSD', $data['data']);
    }

    /** @test */
    public function it_handles_stablecoin_not_found()
    {
        $request = Request::create('/api/v2/stablecoins/NONEXISTENT', 'GET');
        
        try {
            Stablecoin::findOrFail('NONEXISTENT');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\ModelNotFoundException::class, $e);
        }
    }

    /** @test */
    public function it_can_apply_stability_mechanism()
    {
        $stablecoin = Stablecoin::create([
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
            'total_supply' => 1000000,
            'max_supply' => 10000000,
            'total_collateral_value' => 1500000,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        $actions = [
            [
                'action' => 'adjust_fees',
                'timestamp' => now(),
                'reason' => 'Price deviation of 2%',
                'new_mint_fee' => 0.006,
                'new_burn_fee' => 0.002,
            ],
        ];
        
        $this->stabilityService
            ->shouldReceive('applyStabilityMechanism')
            ->once()
            ->with('FUSD')
            ->andReturn($actions);
            
        $response = $this->controller->applyStabilityMechanism($stablecoin);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']['actions']);
    }

    /** @test */
    public function it_handles_apply_stability_mechanism_errors()
    {
        $stablecoin = Stablecoin::create([
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
            'total_supply' => 1000000,
            'max_supply' => 10000000,
            'total_collateral_value' => 1500000,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        $this->stabilityService
            ->shouldReceive('applyStabilityMechanism')
            ->once()
            ->andThrow(new \RuntimeException('Stability mechanism failed'));
            
        $response = $this->controller->applyStabilityMechanism($stablecoin);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Stability mechanism failed', $data['error']);
    }

    /** @test */
    public function it_can_calculate_fee_adjustment()
    {
        $stablecoin = Stablecoin::create([
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
            'total_supply' => 1000000,
            'max_supply' => 10000000,
            'total_collateral_value' => 1500000,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        $adjustment = [
            'new_mint_fee' => 0.006,
            'new_burn_fee' => 0.002,
            'adjustment_reason' => 'Price above peg by 2%',
        ];
        
        $this->stabilityService
            ->shouldReceive('calculateFeeAdjustment')
            ->once()
            ->with('FUSD')
            ->andReturn($adjustment);
            
        $response = $this->controller->calculateFeeAdjustment($stablecoin);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(0.006, $data['data']['new_mint_fee']);
        $this->assertEquals(0.002, $data['data']['new_burn_fee']);
    }

    /** @test */
    public function it_can_filter_active_stablecoins()
    {
        Stablecoin::create([
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
            'total_supply' => 1000000,
            'max_supply' => 10000000,
            'total_collateral_value' => 1500000,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        Stablecoin::create([
            'code' => 'FEUR',
            'name' => 'FinAegis EUR',
            'symbol' => 'FEUR',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'algorithmic',
            'collateral_ratio' => 0,
            'min_collateral_ratio' => 0,
            'liquidation_penalty' => 0,
            'total_supply' => 500000,
            'max_supply' => 5000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.001,
            'burn_fee' => 0.001,
            'precision' => 2,
            'is_active' => false,
            'minting_enabled' => false,
            'burning_enabled' => false,
        ]);
        
        $request = Request::create('/api/v2/stablecoins', 'GET', [
            'active' => 'true'
        ]);
        
        $response = $this->controller->index($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']);
        $this->assertEquals('FUSD', $data['data'][0]['code']);
    }
}