<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use App\Domain\Asset\Models\Asset;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StablecoinCollateralPositionTest extends TestCase
{
    use RefreshDatabase;

    protected Stablecoin $stablecoin;
    protected Account $account;
    protected Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create assets if they don't exist
        $this->asset = Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true
            ]
        );

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

        // Create account
        $this->account = Account::factory()->create();
    }

    /** @test */
    public function it_can_create_a_collateral_position()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
            'auto_liquidation_enabled' => true,
        ]);

        $this->assertEquals($this->account->uuid, $position->account_uuid);
        $this->assertEquals($this->stablecoin->code, $position->stablecoin_code);
        $this->assertEquals(150000, $position->collateral_amount);
        $this->assertEquals(100000, $position->debt_amount);
        $this->assertEquals('active', $position->status);
        $this->assertTrue($position->auto_liquidation_enabled);
    }

    /** @test */
    public function it_has_relationships()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);

        $this->assertInstanceOf(Account::class, $position->account);
        $this->assertInstanceOf(Stablecoin::class, $position->stablecoin);
        $this->assertInstanceOf(Asset::class, $position->collateralAsset);
    }

    /** @test */
    public function it_can_check_if_active()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);

        $this->assertTrue($position->isActive());

        $position->status = 'liquidated';
        $this->assertFalse($position->isActive());
    }

    /** @test */
    public function it_can_check_if_at_risk_of_liquidation()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);

        $this->assertFalse($position->isAtRiskOfLiquidation());

        $position->collateral_ratio = 1.1;
        $this->assertTrue($position->isAtRiskOfLiquidation());
    }

    /** @test */
    public function it_can_check_if_should_auto_liquidate()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
            'auto_liquidation_enabled' => true,
        ]);

        $this->assertFalse($position->shouldAutoLiquidate());

        $position->collateral_ratio = 1.1;
        $this->assertTrue($position->shouldAutoLiquidate());

        $position->auto_liquidation_enabled = false;
        $this->assertFalse($position->shouldAutoLiquidate());

        // Test stop loss
        $position->auto_liquidation_enabled = true;
        $position->collateral_ratio = 1.3;
        $position->stop_loss_ratio = 1.35;
        $this->assertTrue($position->shouldAutoLiquidate());
    }

    /** @test */
    public function it_can_calculate_max_mint_amount()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 150000,
            'debt_amount' => 50000,
            'collateral_ratio' => 3.0,
            'status' => 'active',
        ]);

        $maxMint = $position->calculateMaxMintAmount();
        $this->assertEquals(50000, $maxMint); // (150000 / 1.5) - 50000
    }

    /** @test */
    public function it_can_calculate_liquidation_price()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);

        $liquidationPrice = $position->calculateLiquidationPrice();
        $this->assertEquals(0.8, $liquidationPrice); // (100000 * 1.2) / 150000

        $position->debt_amount = 0;
        $this->assertEquals(0, $position->calculateLiquidationPrice());
    }

    /** @test */
    public function it_can_update_collateral_ratio()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 0,
            'status' => 'active',
        ]);

        $position->updateCollateralRatio();
        $this->assertEquals(1.5, $position->collateral_ratio);

        $position->debt_amount = 0;
        $position->updateCollateralRatio();
        $this->assertEquals(0, $position->collateral_ratio);
    }

    /** @test */
    public function it_can_mark_as_liquidated()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);

        $this->assertNull($position->liquidated_at);

        $position->markAsLiquidated();

        $this->assertEquals('liquidated', $position->status);
        $this->assertNotNull($position->liquidated_at);
    }

    /** @test */
    public function it_has_scopes()
    {
        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();
        $account3 = Account::factory()->create();
        
        StablecoinCollateralPosition::create([
            'account_uuid' => $account1->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);

        StablecoinCollateralPosition::create([
            'account_uuid' => $account2->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 110000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.1,
            'status' => 'active',
            'auto_liquidation_enabled' => true,
        ]);

        StablecoinCollateralPosition::create([
            'account_uuid' => $account3->uuid,
            'stablecoin_code' => $this->stablecoin->code,
            'collateral_asset_code' => $this->asset->code,
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'liquidated',
        ]);

        $this->assertEquals(2, StablecoinCollateralPosition::active()->count());
        $this->assertEquals(1, StablecoinCollateralPosition::atRisk()->count());
        $this->assertEquals(1, StablecoinCollateralPosition::shouldAutoLiquidate()->count());
    }
}