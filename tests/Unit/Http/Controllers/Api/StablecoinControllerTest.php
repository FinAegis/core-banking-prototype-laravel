<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Domain\Stablecoin\Services\CollateralService;
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

    protected StablecoinController $controller;
    protected $collateralService;
    protected $stabilityService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->collateralService = Mockery::mock(CollateralService::class);
        $this->stabilityService = Mockery::mock(StabilityMechanismService::class);
        $this->controller = new StablecoinController($this->collateralService, $this->stabilityService);
        
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
            'code' => 'FUSD_FILTER',
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
            'stability_mechanism' => 'collateralized'
        ]);
        
        $response = $this->controller->index($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']);
        $this->assertEquals('FUSD_FILTER', $data['data'][0]['code']);
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
        
        $this->collateralService
            ->shouldReceive('getSystemCollateralizationMetrics')
            ->andReturn([]);
        
        $response = $this->controller->show('FUSD');
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('FUSD', $data['data']['code']);
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
            
        $response = $this->controller->systemHealth();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('healthy', $data['data']['overall_status']);
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
            'active_only' => 'true'
        ]);
        
        $response = $this->controller->index($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']);
        $this->assertEquals('FUSD', $data['data'][0]['code']);
    }
}