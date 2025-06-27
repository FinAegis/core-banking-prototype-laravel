<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Stablecoin\Services\LiquidationService;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\Account;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use App\Domain\Asset\Models\Asset;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class LiquidationServiceTest extends TestCase
{

    protected LiquidationService $service;
    protected $exchangeRateService;
    protected $collateralService;
    protected $walletService;
    protected Account $account;
    protected Account $liquidatorAccount;
    protected Stablecoin $stablecoin;
    protected Asset $usdAsset;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->exchangeRateService = Mockery::mock(ExchangeRateService::class);
        $this->collateralService = Mockery::mock(CollateralService::class);
        $this->walletService = Mockery::mock(WalletService::class);
        $this->service = new LiquidationService(
            $this->exchangeRateService,
            $this->collateralService,
            $this->walletService
        );
        
        // Create assets
        $this->usdAsset = Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true
            ]
        );
        
        // Create FUSD asset
        Asset::firstOrCreate(
            ['code' => 'FUSD'],
            [
                'name' => 'FinAegis USD',
                'type' => 'stablecoin',
                'precision' => 2,
                'is_active' => true
            ]
        );
        
        // Create accounts
        $this->account = Account::factory()->create();
        $this->liquidatorAccount = Account::factory()->create();
        
        // Create stablecoin
        $this->stablecoin = Stablecoin::create([
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
            'total_collateral_value' => 120000,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        // Add FUSD balance to liquidator account
        \App\Models\AccountBalance::create([
            'account_uuid' => $this->liquidatorAccount->uuid,
            'asset_code' => 'FUSD',
            'balance' => 1000000, // $10,000 FUSD for liquidation
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_calculate_liquidation_reward()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 110000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.1, // Below minimum
            'status' => 'active',
            'auto_liquidation_enabled' => true,
        ]);
        
        $reward = $this->service->calculateLiquidationReward($position);
        
        $this->assertArrayHasKey('penalty', $reward);
        $this->assertArrayHasKey('reward', $reward);
        $this->assertArrayHasKey('collateral_seized', $reward);
        $this->assertArrayHasKey('eligible', $reward);
        $this->assertTrue($reward['eligible']);
        $this->assertEquals(11000, $reward['penalty']); // 10% penalty on 110,000 collateral
    }

    /** @test */
    public function it_prevents_liquidation_of_healthy_positions()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 200000,
            'debt_amount' => 100000,
            'collateral_ratio' => 2.0, // Healthy
            'status' => 'active',
        ]);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Position is not eligible for liquidation');
        
        $this->service->liquidatePosition($position);
    }

    /** @test */
    public function it_can_liquidate_a_position()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 110000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.1,
            'status' => 'active',
        ]);
        
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 110000, 'USD')
            ->once()
            ->andReturn(110000);
            
        Event::fake();
        
        $result = $this->service->liquidatePosition(
            $position,
            $this->liquidatorAccount
        );
        
        $this->assertTrue($result['success']);
        $this->assertEquals(100000, $result['debt_repaid']);
        $this->assertEquals(110000, $result['collateral_seized']);
        $this->assertEquals(10000, $result['penalty_amount']);
        $this->assertEquals(0, $result['remaining_collateral']);
        
        // Check position was marked as liquidated
        $this->assertEquals('liquidated', $position->fresh()->status);
        $this->assertNotNull($position->fresh()->liquidated_at);
        
        // Check balances
        $this->assertEquals(0, $this->liquidatorAccount->getBalance('FUSD')); // Repaid debt
        $this->assertEquals(100000, $this->liquidatorAccount->getBalance('USD')); // Received collateral minus penalty
        
        // Check stablecoin stats
        $this->stablecoin->refresh();
        $this->assertEquals(0, $this->stablecoin->total_supply);
        $this->assertEquals(10000, $this->stablecoin->total_collateral_value); // Only penalty remains
    }

    /** @test */
    public function it_can_perform_partial_liquidation()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 110000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.1,
            'status' => 'active',
        ]);
        
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 55000, 'USD')
            ->once()
            ->andReturn(55000);
            
        $result = $this->service->liquidatePosition(
            $position,
            $this->liquidatorAccount,
            50000 // Partial liquidation
        );
        
        $this->assertTrue($result['success']);
        $this->assertEquals(50000, $result['debt_repaid']);
        $this->assertEquals(55000, $result['collateral_seized']);
        $this->assertEquals(5000, $result['penalty_amount']);
        $this->assertEquals(55000, $result['remaining_collateral']);
        
        // Position should still be active
        $position->refresh();
        $this->assertEquals('active', $position->status);
        $this->assertEquals(50000, $position->debt_amount);
        $this->assertEquals(55000, $position->collateral_amount);
    }

    /** @test */
    public function it_validates_liquidator_balance()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 110000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.1,
            'status' => 'active',
        ]);
        
        $poorLiquidator = Account::factory()->create();
        \App\Models\AccountBalance::create([
            'account_uuid' => $poorLiquidator->uuid,
            'asset_code' => 'FUSD',
            'balance' => 50000, // Only $500 FUSD
        ]);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient FUSD balance for liquidation');
        
        $this->service->liquidatePosition(
            $position,
            $poorLiquidator,
            100000 // Wants to liquidate $1,000 but only has $500
        );
    }

    /** @test */
    public function it_prevents_self_liquidation()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 110000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.1,
            'status' => 'active',
        ]);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot liquidate your own position');
        
        $this->service->liquidatePosition(
            $position,
            $this->account, // Same account as position owner
            100000
        );
    }

    /** @test */
    public function it_can_get_liquidation_opportunities()
    {
        // Create multiple positions
        StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 110000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.1, // At risk
            'status' => 'active',
        ]);
        
        $account2 = Account::factory()->create();
        StablecoinCollateralPosition::create([
            'account_uuid' => $account2->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 200000,
            'debt_amount' => 100000,
            'collateral_ratio' => 2.0, // Healthy
            'status' => 'active',
        ]);
        
        $account3 = Account::factory()->create();
        StablecoinCollateralPosition::create([
            'account_uuid' => $account3->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 105000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.05, // Very risky
            'status' => 'active',
        ]);
        
        $this->collateralService
            ->shouldReceive('calculatePositionHealthScore')
            ->andReturn(0.1, 0.05);
            
        $this->collateralService
            ->shouldReceive('calculateLiquidationPriority')
            ->andReturn(0.8, 0.95);
            
        $opportunities = $this->service->getLiquidationOpportunities('FUSD');
        
        $this->assertCount(2, $opportunities);
        // Should be sorted by priority (highest first)
        $this->assertEquals(1.05, $opportunities[0]['current_ratio']);
        $this->assertEquals(1.1, $opportunities[1]['current_ratio']);
    }

    /** @test */
    public function it_can_process_auto_liquidations()
    {
        // Create positions eligible for auto-liquidation
        $position1 = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 110000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.1,
            'status' => 'active',
            'auto_liquidation_enabled' => true,
        ]);
        
        $account2 = Account::factory()->create();
        $position2 = StablecoinCollateralPosition::create([
            'account_uuid' => $account2->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 105000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.05,
            'status' => 'active',
            'auto_liquidation_enabled' => false, // Won't be auto-liquidated
        ]);
        
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 110000, 'USD')
            ->once()
            ->andReturn(110000);
            
        // Create system liquidator account
        $systemAccount = Account::factory()->create(['uuid' => 'system-liquidator']);
        \App\Models\AccountBalance::create([
            'account_uuid' => $systemAccount->uuid,
            'asset_code' => 'FUSD',
            'balance' => 1000000,
        ]);
        
        $results = $this->service->processAutoLiquidations('FUSD');
        
        $this->assertCount(1, $results);
        $this->assertEquals($position1->uuid, $results[0]['position_uuid']);
        $this->assertTrue($results[0]['success']);
        
        // Check position was liquidated
        $this->assertEquals('liquidated', $position1->fresh()->status);
        $this->assertEquals('active', $position2->fresh()->status);
    }

    /** @test */
    public function it_can_estimate_liquidation_cascade()
    {
        // Create positions that could trigger cascade
        StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 125000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.25,
            'status' => 'active',
        ]);
        
        $account2 = Account::factory()->create();
        StablecoinCollateralPosition::create([
            'account_uuid' => $account2->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 130000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.3,
            'status' => 'active',
        ]);
        
        $cascade = $this->service->estimateLiquidationCascade('FUSD', 0.9); // 10% price drop
        
        $this->assertEquals(2, $cascade['positions_affected']);
        $this->assertEquals(200000, $cascade['total_debt_at_risk']);
        $this->assertEquals(255000, $cascade['total_collateral_at_risk']);
        $this->assertCount(2, $cascade['affected_positions']);
    }

    /** @test */
    public function it_prevents_liquidation_of_inactive_positions()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 110000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.1,
            'status' => 'frozen', // Not active
        ]);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Position is not active');
        
        $this->service->liquidatePosition(
            $position,
            $this->liquidatorAccount,
            100000
        );
    }

    /** @test */
    public function it_handles_liquidation_with_different_collateral_asset()
    {
        $eurAsset = Asset::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true
            ]
        );
        
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'EUR',
            'collateral_amount' => 100000, // €1,000
            'debt_amount' => 100000, // $1,000 debt
            'collateral_ratio' => 1.1,
            'status' => 'active',
        ]);
        
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('EUR', 100000, 'USD')
            ->once()
            ->andReturn(110000); // EUR worth more than USD
            
        $result = $this->service->liquidatePosition(
            $position,
            $this->liquidatorAccount,
            100000
        );
        
        $this->assertTrue($result['success']);
        $this->assertEquals(100000, $result['collateral_seized']); // EUR amount
        $this->assertEquals(90000, $this->liquidatorAccount->getBalance('EUR')); // After penalty
    }

    /** @test */
    public function it_updates_stablecoin_stats_after_liquidation()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 110000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.1,
            'status' => 'active',
        ]);
        
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 110000, 'USD')
            ->once()
            ->andReturn(110000);
            
        $initialSupply = $this->stablecoin->total_supply;
        $initialCollateral = $this->stablecoin->total_collateral_value;
        
        $this->service->liquidatePosition(
            $position,
            $this->liquidatorAccount,
            100000
        );
        
        $this->stablecoin->refresh();
        $this->assertEquals($initialSupply - 100000, $this->stablecoin->total_supply);
        $this->assertEquals($initialCollateral - 100000, $this->stablecoin->total_collateral_value);
    }
}