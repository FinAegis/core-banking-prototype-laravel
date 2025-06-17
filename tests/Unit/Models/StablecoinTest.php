<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use App\Domain\Asset\Models\Asset;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StablecoinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create assets for testing
        Asset::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'type' => 'fiat',
            'precision' => 2,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_can_create_a_stablecoin()
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

        $this->assertEquals('FUSD', $stablecoin->code);
        $this->assertEquals('FinAegis USD', $stablecoin->name);
        $this->assertEquals('collateralized', $stablecoin->stability_mechanism);
        $this->assertEquals(1.5, $stablecoin->collateral_ratio);
        $this->assertTrue($stablecoin->is_active);
    }

    /** @test */
    public function it_can_check_if_minting_is_allowed()
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

        $this->assertTrue($stablecoin->canMint());

        $stablecoin->minting_enabled = false;
        $this->assertFalse($stablecoin->canMint());

        $stablecoin->minting_enabled = true;
        $stablecoin->is_active = false;
        $this->assertFalse($stablecoin->canMint());
    }

    /** @test */
    public function it_can_check_if_burning_is_allowed()
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

        $this->assertTrue($stablecoin->canBurn());

        $stablecoin->burning_enabled = false;
        $this->assertFalse($stablecoin->canBurn());

        $stablecoin->burning_enabled = true;
        $stablecoin->is_active = false;
        $this->assertFalse($stablecoin->canBurn());
    }

    /** @test */
    public function it_can_check_if_max_supply_is_reached()
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

        $this->assertFalse($stablecoin->hasReachedMaxSupply());

        $stablecoin->total_supply = 10000000;
        $this->assertTrue($stablecoin->hasReachedMaxSupply());

        $stablecoin->max_supply = null;
        $this->assertFalse($stablecoin->hasReachedMaxSupply());
    }

    /** @test */
    public function it_can_calculate_global_collateralization_ratio()
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

        $this->assertEquals(1.5, $stablecoin->calculateGlobalCollateralizationRatio());

        $stablecoin->total_supply = 0;
        $this->assertEquals(0, $stablecoin->calculateGlobalCollateralizationRatio());
    }

    /** @test */
    public function it_can_check_if_adequately_collateralized()
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

        $this->assertTrue($stablecoin->isAdequatelyCollateralized());

        $stablecoin->total_collateral_value = 100000;
        $this->assertFalse($stablecoin->isAdequatelyCollateralized());
    }

    /** @test */
    public function it_has_proper_scopes()
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

        Stablecoin::create([
            'code' => 'FEUR',
            'name' => 'FinAegis EUR',
            'symbol' => 'FEUR',
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

        $this->assertEquals(1, Stablecoin::active()->count());
        $this->assertEquals(1, Stablecoin::mintingEnabled()->count());
        $this->assertEquals(1, Stablecoin::burningEnabled()->count());
    }
}