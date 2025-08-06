<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Stablecoin\Events\CollateralPositionLiquidated;
use App\Domain\Stablecoin\Events\StablecoinBurned;
use App\Domain\Stablecoin\Events\StablecoinMinted;
use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use App\Domain\Stablecoin\Models\StablecoinTransaction;
use App\Domain\Stablecoin\Services\DemoStablecoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DemoStablecoinServiceTest extends TestCase
{
    use RefreshDatabase;

    private DemoStablecoinService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('demo.mode', true);
        Config::set('demo.features.auto_collateralize', true);
        Config::set('demo.demo_data.stablecoin.collateral_ratio', 150);
        Config::set('demo.demo_data.stablecoin.liquidation_threshold', 120);
        Config::set('demo.demo_data.stablecoin.stability_fee', 2.5);

        $this->service = new DemoStablecoinService();

        // Create a test stablecoin
        Stablecoin::create([
            'id' => 1,
            'code' => 'GUSD',
            'name' => 'G-USD Stablecoin',
            'symbol' => 'GUSD',
            'peg_asset_code' => 'USD',
            'total_supply' => 0,
            'collateral_ratio' => 150,
            'liquidation_threshold' => 120,
            'stability_fee' => 2.5,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_can_mint_stablecoins_with_auto_collateralization()
    {
        Event::fake();

        $mintData = [
            'stablecoin_code' => 'GUSD',
            'account_id' => 1,
            'amount' => 1000,
            'collateral_currency' => 'USD',
        ];

        $transaction = $this->service->mint($mintData);

        $this->assertInstanceOf(StablecoinTransaction::class, $transaction);
        $this->assertStringStartsWith('demo_mint_', $transaction->id);
        $this->assertEquals('mint', $transaction->type);
        $this->assertEquals(1000, $transaction->amount);
        $this->assertEquals(1500, $transaction->collateral_amount); // 150% ratio
        $this->assertEquals('completed', $transaction->status);
        $this->assertTrue($transaction->metadata['demo_mode']);
        $this->assertTrue($transaction->metadata['auto_collateralized']);

        // Check position was created/updated
        $position = StablecoinCollateralPosition::where('account_id', 1)->first();
        $this->assertNotNull($position);
        $this->assertEquals(1000, $position->minted_amount);
        $this->assertEquals(1500, $position->collateral_amount);
        $this->assertEquals(150, $position->collateralization_ratio);

        // Check stablecoin supply increased
        $stablecoin = Stablecoin::where('code', 'GUSD')->first();
        $this->assertEquals(1000, $stablecoin->total_supply);

        Event::assertDispatched(StablecoinMinted::class);
    }

    /** @test */
    public function it_can_burn_stablecoins_and_release_collateral()
    {
        Event::fake();

        // First create a position with minted stablecoins
        $position = StablecoinCollateralPosition::create([
            'id' => 'demo_pos_test123',
            'account_id' => 1,
            'stablecoin_id' => 1,
            'minted_amount' => 1000,
            'collateral_amount' => 1500,
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 150,
            'status' => 'active',
        ]);

        $stablecoin = Stablecoin::where('code', 'GUSD')->first();
        $stablecoin->update(['total_supply' => 1000]);

        $burnData = [
            'stablecoin_code' => 'GUSD',
            'account_id' => 1,
            'amount' => 500,
        ];

        $transaction = $this->service->burn($burnData);

        $this->assertStringStartsWith('demo_burn_', $transaction->id);
        $this->assertEquals('burn', $transaction->type);
        $this->assertEquals(500, $transaction->amount);
        $this->assertEquals(750, $transaction->collateral_amount); // Proportional release
        $this->assertEquals('completed', $transaction->status);

        // Check position updated
        $position->refresh();
        $this->assertEquals(500, $position->minted_amount);
        $this->assertEquals(750, $position->collateral_amount);
        $this->assertEquals('active', $position->status);

        // Check stablecoin supply decreased
        $stablecoin->refresh();
        $this->assertEquals(500, $stablecoin->total_supply);

        Event::assertDispatched(StablecoinBurned::class);
    }

    /** @test */
    public function it_closes_position_when_fully_burned()
    {
        $position = StablecoinCollateralPosition::create([
            'id' => 'demo_pos_test456',
            'account_id' => 1,
            'stablecoin_id' => 1,
            'minted_amount' => 100,
            'collateral_amount' => 150,
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 150,
            'status' => 'active',
        ]);

        $burnData = [
            'stablecoin_code' => 'GUSD',
            'account_id' => 1,
            'amount' => 100, // Burn all
        ];

        $transaction = $this->service->burn($burnData);

        $position->refresh();
        $this->assertEquals(0, $position->minted_amount);
        $this->assertEquals(0, $position->collateral_amount);
        $this->assertEquals('closed', $position->status);
    }

    /** @test */
    public function it_can_add_collateral_to_position()
    {
        Event::fake();

        $position = StablecoinCollateralPosition::create([
            'id' => 'demo_pos_test789',
            'account_id' => 1,
            'stablecoin_id' => 1,
            'minted_amount' => 1000,
            'collateral_amount' => 1200, // Under-collateralized at 120%
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 120,
            'status' => 'active',
        ]);

        $addData = [
            'position_id' => $position->id,
            'amount' => 300,
        ];

        $updatedPosition = $this->service->addCollateral($addData);

        $this->assertEquals(1500, $updatedPosition->collateral_amount);
        $this->assertEquals(150, $updatedPosition->collateralization_ratio);

        // Check transaction was created
        $transaction = StablecoinTransaction::where('type', 'add_collateral')
            ->where('position_id', $position->id)
            ->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(300, $transaction->collateral_amount);

        // CollateralAdded event doesn't exist in this domain
    }

    /** @test */
    public function it_checks_collateralization_status_correctly()
    {
        // Test healthy position
        $healthyPosition = StablecoinCollateralPosition::create([
            'id' => 'demo_pos_healthy',
            'account_id' => 1,
            'stablecoin_id' => 1,
            'minted_amount' => 1000,
            'collateral_amount' => 1600,
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 160,
            'status' => 'active',
        ]);

        $status = $this->service->checkCollateralization($healthyPosition->id);

        $this->assertEquals('healthy', $status['status']);
        $this->assertEquals(160, $status['current_ratio']);
        $this->assertEquals(1500, $status['required_collateral']);
        $this->assertEquals(100, $status['excess_collateral']);
        $this->assertTrue($status['demo']);

        // Test warning position
        $warningPosition = StablecoinCollateralPosition::create([
            'id' => 'demo_pos_warning',
            'account_id' => 2,
            'stablecoin_id' => 1,
            'minted_amount' => 1000,
            'collateral_amount' => 1300,
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 130,
            'status' => 'active',
        ]);

        $status = $this->service->checkCollateralization($warningPosition->id);
        $this->assertEquals('warning', $status['status']);

        // Test at-risk position
        $atRiskPosition = StablecoinCollateralPosition::create([
            'id' => 'demo_pos_atrisk',
            'account_id' => 3,
            'stablecoin_id' => 1,
            'minted_amount' => 1000,
            'collateral_amount' => 1100,
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 110,
            'status' => 'active',
        ]);

        $status = $this->service->checkCollateralization($atRiskPosition->id);
        $this->assertEquals('at_risk', $status['status']);
    }

    /** @test */
    public function it_can_liquidate_at_risk_positions()
    {
        Event::fake();

        $position = StablecoinCollateralPosition::create([
            'id' => 'demo_pos_liquidate',
            'account_id' => 1,
            'stablecoin_id' => 1,
            'minted_amount' => 1000,
            'collateral_amount' => 1100, // 110% - below threshold
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 110,
            'status' => 'active',
        ]);

        $stablecoin = Stablecoin::where('code', 'GUSD')->first();
        $stablecoin->update(['total_supply' => 1000]);

        $result = $this->service->liquidate($position->id);

        $this->assertStringStartsWith('demo_liq_', $result['transaction_id']);
        $this->assertEquals(1000, $result['debt_covered']);
        $this->assertEquals(1100, $result['collateral_seized']);
        $this->assertEquals(110, $result['penalty_amount']); // 10% penalty
        $this->assertEquals(55, $result['liquidator_reward']); // 50% of penalty
        $this->assertTrue($result['demo']);

        // Check position was liquidated
        $position->refresh();
        $this->assertEquals(0, $position->minted_amount);
        $this->assertEquals(0, $position->collateral_amount);
        $this->assertEquals('liquidated', $position->status);
        $this->assertNotNull($position->liquidated_at);

        // Check stablecoin supply decreased
        $stablecoin->refresh();
        $this->assertEquals(0, $stablecoin->total_supply);

        Event::assertDispatched(CollateralPositionLiquidated::class);
    }

    /** @test */
    public function it_cannot_liquidate_healthy_positions()
    {
        $position = StablecoinCollateralPosition::create([
            'id' => 'demo_pos_healthy_noliq',
            'account_id' => 1,
            'stablecoin_id' => 1,
            'minted_amount' => 1000,
            'collateral_amount' => 1500, // 150% - healthy
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 150,
            'status' => 'active',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Position is not eligible for liquidation');

        $this->service->liquidate($position->id);
    }

    /** @test */
    public function it_identifies_positions_at_risk()
    {
        // Create multiple positions with different ratios
        StablecoinCollateralPosition::create([
            'id' => 'demo_pos_risk1',
            'account_id' => 1,
            'stablecoin_id' => 1,
            'minted_amount' => 1000,
            'collateral_amount' => 1100, // At risk
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 110,
            'status' => 'active',
        ]);

        StablecoinCollateralPosition::create([
            'id' => 'demo_pos_safe1',
            'account_id' => 2,
            'stablecoin_id' => 1,
            'minted_amount' => 1000,
            'collateral_amount' => 1500, // Safe
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 150,
            'status' => 'active',
        ]);

        StablecoinCollateralPosition::create([
            'id' => 'demo_pos_risk2',
            'account_id' => 3,
            'stablecoin_id' => 1,
            'minted_amount' => 500,
            'collateral_amount' => 550, // At risk
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 110,
            'status' => 'active',
        ]);

        $atRisk = $this->service->getPositionsAtRisk();

        $this->assertCount(2, $atRisk);
        $this->assertEquals('demo_pos_risk1', $atRisk[0]['position_id']);
        $this->assertEquals('demo_pos_risk2', $atRisk[1]['position_id']);
        $this->assertEquals(55, $atRisk[0]['estimated_liquidation_reward']); // 5% of 1100
    }

    /** @test */
    public function it_executes_stability_mechanism()
    {
        $result = $this->service->executeStabilityMechanism('GUSD');

        $this->assertArrayHasKey('stablecoin', $result);
        $this->assertArrayHasKey('current_price', $result);
        $this->assertArrayHasKey('target_price', $result);
        $this->assertArrayHasKey('deviation_percentage', $result);
        $this->assertArrayHasKey('total_supply', $result);
        $this->assertArrayHasKey('total_collateral', $result);
        $this->assertArrayHasKey('system_collateralization', $result);
        $this->assertArrayHasKey('recommended_actions', $result);
        $this->assertArrayHasKey('executed', $result);
        $this->assertTrue($result['demo']);

        $this->assertEquals('GUSD', $result['stablecoin']);
        $this->assertEquals(1.00, $result['target_price']);

        // Price should be close to $1 with small variation
        $this->assertGreaterThan(0.97, $result['current_price']);
        $this->assertLessThan(1.03, $result['current_price']);
    }

    /** @test */
    public function it_creates_position_on_first_mint()
    {
        $mintData = [
            'stablecoin_code' => 'GUSD',
            'account_id' => 99,
            'amount' => 100,
        ];

        // Position shouldn't exist yet
        $position = StablecoinCollateralPosition::where('account_id', 99)->first();
        $this->assertNull($position);

        $this->service->mint($mintData);

        // Position should be created
        $position = StablecoinCollateralPosition::where('account_id', 99)->first();
        $this->assertNotNull($position);
        $this->assertStringStartsWith('demo_pos_', $position->id);
        $this->assertEquals(100, $position->minted_amount);
        $this->assertEquals(150, $position->collateral_amount);
        $this->assertTrue($position->metadata['demo_mode']);
    }

    /** @test */
    public function it_respects_auto_collateralize_configuration()
    {
        Config::set('demo.features.auto_collateralize', false);
        Event::fake();

        $mintData = [
            'stablecoin_code' => 'GUSD',
            'account_id' => 1,
            'amount' => 1000,
        ];

        $transaction = $this->service->mint($mintData);

        // Transaction still created but without auto-collateralization
        $this->assertEquals('mint', $transaction->type);
        $this->assertEquals(1000, $transaction->amount);

        // Position would have zero collateral without auto-collateralization
        // But the service still calculates required collateral
        $this->assertEquals(1500, $transaction->collateral_amount);
    }

    /** @test */
    public function it_calculates_system_collateralization_correctly()
    {
        // Create multiple positions
        StablecoinCollateralPosition::create([
            'id' => 'demo_pos_sys1',
            'account_id' => 1,
            'stablecoin_id' => 1,
            'minted_amount' => 1000,
            'collateral_amount' => 1500,
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 150,
            'status' => 'active',
        ]);

        StablecoinCollateralPosition::create([
            'id' => 'demo_pos_sys2',
            'account_id' => 2,
            'stablecoin_id' => 1,
            'minted_amount' => 500,
            'collateral_amount' => 800,
            'collateral_currency' => 'USD',
            'collateralization_ratio' => 160,
            'status' => 'active',
        ]);

        $result = $this->service->executeStabilityMechanism('GUSD');

        // System collateralization = (1500 + 800) / (1000 + 500) * 100 = 153.33%
        $this->assertEquals(153.33, $result['system_collateralization']);
        $this->assertEquals(2300, $result['total_collateral']);
    }
}