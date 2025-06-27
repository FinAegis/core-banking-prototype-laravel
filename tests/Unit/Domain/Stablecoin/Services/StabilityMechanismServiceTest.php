<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Stablecoin\Services\StabilityMechanismService;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use App\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class StabilityMechanismServiceTest extends TestCase
{

    protected StabilityMechanismService $service;
    protected $exchangeRateService;
    protected $collateralService;
    protected Stablecoin $collateralizedStablecoin;
    protected Stablecoin $algorithmicStablecoin;
    protected Stablecoin $hybridStablecoin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->exchangeRateService = Mockery::mock(ExchangeRateService::class);
        $this->collateralService = Mockery::mock(CollateralService::class);
        $this->service = new StabilityMechanismService(
            $this->exchangeRateService,
            $this->collateralService
        );
        
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
        
        // Create different types of stablecoins
        $this->collateralizedStablecoin = Stablecoin::create([
            'code' => 'CUSD',
            'name' => 'Collateralized USD',
            'symbol' => 'CUSD',
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
            'algo_mint_reward' => 0,
            'algo_burn_penalty' => 0,
        ]);
        
        $this->algorithmicStablecoin = Stablecoin::create([
            'code' => 'AUSD',
            'name' => 'Algorithmic USD',
            'symbol' => 'AUSD',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'algorithmic',
            'collateral_ratio' => 0,
            'min_collateral_ratio' => 0,
            'liquidation_penalty' => 0,
            'total_supply' => 1000000,
            'max_supply' => 50000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.001,
            'burn_fee' => 0.001,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
            'algo_mint_reward' => 0.02,
            'algo_burn_penalty' => 0.02,
        ]);
        
        $this->hybridStablecoin = Stablecoin::create([
            'code' => 'HUSD',
            'name' => 'Hybrid USD',
            'symbol' => 'HUSD',
            'peg_asset_code' => 'USD',
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'hybrid',
            'collateral_ratio' => 0.8, // 80% collateralized
            'min_collateral_ratio' => 0.5,
            'liquidation_penalty' => 0.08,
            'total_supply' => 1000000,
            'max_supply' => 20000000,
            'total_collateral_value' => 800000,
            'mint_fee' => 0.003,
            'burn_fee' => 0.002,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
            'algo_mint_reward' => 0.01,
            'algo_burn_penalty' => 0.01,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Create a mock ExchangeRate object with the given rate.
     */
    private function createMockRate(float $rate, string $from = 'FUSD', string $to = 'USD'): ExchangeRate
    {
        return new ExchangeRate([
            'from_asset_code' => $from,
            'to_asset_code' => $to,
            'rate' => $rate,
            'source' => ExchangeRate::SOURCE_API,
            'valid_at' => now(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_check_peg_deviation()
    {
        // Mock current price above peg
        $mockRate = $this->createMockRate(1.05, 'CUSD', 'USD'); // 5% above peg
        
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('CUSD', 'USD')
            ->once()
            ->andReturn($mockRate);
            
        $deviation = $this->service->checkPegDeviation('CUSD');
        
        $this->assertEqualsWithDelta(0.05, $deviation['deviation'], 0.0001);
        $this->assertEqualsWithDelta(5.0, $deviation['percentage'], 0.0001);
        $this->assertEquals('above', $deviation['direction']);
        $this->assertFalse($deviation['within_threshold']);
        
        // Mock current price below peg
        $mockRate2 = $this->createMockRate(0.97, 'CUSD', 'USD'); // 3% below peg
        
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('CUSD', 'USD')
            ->once()
            ->andReturn($mockRate2);
            
        $deviation = $this->service->checkPegDeviation('CUSD');
        
        $this->assertEqualsWithDelta(-0.03, $deviation['deviation'], 0.0001);
        $this->assertEqualsWithDelta(-3.0, $deviation['percentage'], 0.0001);
        $this->assertEquals('below', $deviation['direction']);
        $this->assertFalse($deviation['within_threshold']);
    }

    /** @test */
    public function it_can_apply_collateralized_stability_mechanism()
    {
        $account = Account::factory()->create();
        
        // Create some positions
        StablecoinCollateralPosition::create([
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'CUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        // Price above peg - should increase fees to discourage minting
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('CUSD', 'USD')
            ->once()
            ->andReturn($this->createMockRate(1.05));
            
        $actions = $this->service->applyStabilityMechanism('CUSD');
        
        $this->assertContains('adjust_fees', array_column($actions, 'action'));
        $feeAction = collect($actions)->firstWhere('action', 'adjust_fees');
        $this->assertGreaterThan(0.005, $feeAction['new_mint_fee']); // Higher than base
        $this->assertLessThan(0.003, $feeAction['new_burn_fee']); // Lower than base
        
        // Check that fees were actually updated
        $this->collateralizedStablecoin->refresh();
        $this->assertGreaterThan(0.005, $this->collateralizedStablecoin->mint_fee);
    }

    /** @test */
    public function it_can_apply_algorithmic_stability_mechanism()
    {
        // Price below peg - should incentivize burning
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('AUSD', 'USD')
            ->once()
            ->andReturn($this->createMockRate(0.95));
            
        $actions = $this->service->applyStabilityMechanism('AUSD');
        
        $this->assertContains('adjust_supply', array_column($actions, 'action'));
        $supplyAction = collect($actions)->firstWhere('action', 'adjust_supply');
        $this->assertEquals('contract', $supplyAction['direction']);
        $this->assertGreaterThan(0, $supplyAction['burn_incentive']);
        
        // Check rewards were updated
        $this->algorithmicStablecoin->refresh();
        $this->assertGreaterThan(0.02, $this->algorithmicStablecoin->algo_burn_penalty); // Increased incentive
    }

    /** @test */
    public function it_can_apply_hybrid_stability_mechanism()
    {
        $account = Account::factory()->create();
        
        StablecoinCollateralPosition::create([
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'HUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 80000,
            'debt_amount' => 100000,
            'collateral_ratio' => 0.8,
            'status' => 'active',
        ]);
        
        // Price above peg
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('HUSD', 'USD')
            ->once()
            ->andReturn($this->createMockRate(1.03));
            
        $actions = $this->service->applyStabilityMechanism('HUSD');
        
        // Should apply both fee adjustments and supply incentives
        $this->assertContains('adjust_fees', array_column($actions, 'action'));
        $this->assertContains('adjust_incentives', array_column($actions, 'action'));
        
        // Check updates
        $this->hybridStablecoin->refresh();
        $this->assertGreaterThan(0.003, $this->hybridStablecoin->mint_fee);
        $this->assertLessThan(0.01, $this->hybridStablecoin->algo_mint_reward);
    }

    /** @test */
    public function it_calculates_fee_adjustments_based_on_deviation()
    {
        // Test various deviations
        $testCases = [
            ['current_price' => 1.10, 'expected_mint_fee_increase' => true, 'expected_burn_fee_decrease' => true],
            ['current_price' => 0.90, 'expected_mint_fee_increase' => false, 'expected_burn_fee_decrease' => false],
            ['current_price' => 1.001, 'expected_mint_fee_increase' => false, 'expected_burn_fee_decrease' => false], // Within threshold
        ];
        
        foreach ($testCases as $case) {
            $this->exchangeRateService
                ->shouldReceive('getRate')
                ->with('CUSD', 'USD')
                ->once()
                ->andReturn($this->createMockRate($case['current_price']));
                
            $adjustment = $this->service->calculateFeeAdjustment('CUSD');
            
            if ($case['expected_mint_fee_increase']) {
                $this->assertGreaterThan($this->collateralizedStablecoin->mint_fee, $adjustment['new_mint_fee']);
            } else {
                $this->assertLessThanOrEqual($this->collateralizedStablecoin->mint_fee, $adjustment['new_mint_fee']);
            }
            
            if ($case['expected_burn_fee_decrease']) {
                $this->assertLessThan($this->collateralizedStablecoin->burn_fee, $adjustment['new_burn_fee']);
            } else {
                $this->assertGreaterThanOrEqual($this->collateralizedStablecoin->burn_fee, $adjustment['new_burn_fee']);
            }
        }
    }

    /** @test */
    public function it_can_monitor_all_stablecoin_pegs()
    {
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('CUSD', 'USD')
            ->once()
            ->andReturn($this->createMockRate(1.02));
            
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('AUSD', 'USD')
            ->once()
            ->andReturn($this->createMockRate(0.98));
            
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('HUSD', 'USD')
            ->once()
            ->andReturn($this->createMockRate(1.0));
            
        $monitoring = $this->service->monitorAllPegs();
        
        $this->assertCount(3, $monitoring);
        
        // Check CUSD
        $cusdStatus = collect($monitoring)->firstWhere('stablecoin_code', 'CUSD');
        $this->assertEquals('above', $cusdStatus['deviation']['direction']);
        $this->assertEquals('warning', $cusdStatus['status']);
        
        // Check AUSD
        $ausdStatus = collect($monitoring)->firstWhere('stablecoin_code', 'AUSD');
        $this->assertEquals('below', $ausdStatus['deviation']['direction']);
        $this->assertEquals('warning', $ausdStatus['status']);
        
        // Check HUSD
        $husdStatus = collect($monitoring)->firstWhere('stablecoin_code', 'HUSD');
        $this->assertEquals('at', $husdStatus['deviation']['direction']);
        $this->assertEquals('healthy', $husdStatus['status']);
    }

    /** @test */
    public function it_can_execute_emergency_actions()
    {
        // Extreme price deviation
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('CUSD', 'USD')
            ->once()
            ->andReturn($this->createMockRate(1.5)); // 50% above peg!
            
        Event::fake();
        
        $actions = $this->service->executeEmergencyActions('CUSD');
        
        $this->assertContains('pause_minting', array_column($actions, 'action'));
        $this->assertContains('max_fee_adjustment', array_column($actions, 'action'));
        
        // Check minting was paused
        $this->collateralizedStablecoin->refresh();
        $this->assertFalse($this->collateralizedStablecoin->minting_enabled);
        
        // Check fees were maxed out
        $this->assertEquals(0.1, $this->collateralizedStablecoin->mint_fee); // Max fee
    }

    /** @test */
    public function it_can_calculate_supply_incentives()
    {
        // Price below peg - need to reduce supply
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('AUSD', 'USD')
            ->once()
            ->andReturn($this->createMockRate(0.92));
            
        $incentives = $this->service->calculateSupplyIncentives('AUSD');
        
        $this->assertEquals('burn', $incentives['recommended_action']);
        $this->assertGreaterThan(0, $incentives['burn_reward']);
        $this->assertEquals(0, $incentives['mint_penalty']);
        
        // Price above peg - need to increase supply
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('AUSD', 'USD')
            ->once()
            ->andReturn($this->createMockRate(1.08));
            
        $incentives = $this->service->calculateSupplyIncentives('AUSD');
        
        $this->assertEquals('mint', $incentives['recommended_action']);
        $this->assertGreaterThan(0, $incentives['mint_reward']);
        $this->assertEquals(0, $incentives['burn_penalty']);
    }

    /** @test */
    public function it_respects_fee_bounds()
    {
        // Extreme deviation should still respect max fees
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('CUSD', 'USD')
            ->once()
            ->andReturn($this->createMockRate(2.0)); // 100% above peg
            
        $adjustment = $this->service->calculateFeeAdjustment('CUSD');
        
        $this->assertLessThanOrEqual(0.1, $adjustment['new_mint_fee']); // Max 10%
        $this->assertGreaterThanOrEqual(0, $adjustment['new_burn_fee']); // Min 0%
    }

    /** @test */
    public function it_can_get_stability_recommendations()
    {
        // Under-collateralized situation
        $this->collateralizedStablecoin->total_collateral_value = 1000000; // Equal to supply
        $this->collateralizedStablecoin->save();
        
        $recommendations = $this->service->getStabilityRecommendations('CUSD');
        
        $this->assertContains('increase_collateral_requirements', array_column($recommendations, 'action'));
        $this->assertContains('incentivize_collateral_deposits', array_column($recommendations, 'action'));
        
        // Over-supplied algorithmic stablecoin
        $this->algorithmicStablecoin->total_supply = 40000000; // Near max
        $this->algorithmicStablecoin->save();
        
        $recommendations = $this->service->getStabilityRecommendations('AUSD');
        
        $this->assertContains('reduce_max_supply', array_column($recommendations, 'action'));
        $this->assertContains('increase_burn_incentives', array_column($recommendations, 'action'));
    }

    /** @test */
    public function it_tracks_stability_mechanism_history()
    {
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('CUSD', 'USD')
            ->once()
            ->andReturn($this->createMockRate(1.05));
            
        Event::fake();
        
        $actions = $this->service->applyStabilityMechanism('CUSD');
        
        Event::assertDispatched('stability.mechanism.applied');
        
        // Verify action history is tracked
        $this->assertGreaterThan(0, count($actions));
        foreach ($actions as $action) {
            $this->assertArrayHasKey('timestamp', $action);
            $this->assertArrayHasKey('action', $action);
            $this->assertArrayHasKey('reason', $action);
        }
    }
}