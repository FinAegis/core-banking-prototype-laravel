<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use App\Domain\Asset\Models\Asset;
use App\Models\Account;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StablecoinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create assets for testing if they don't exist
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

    /** @test */
    public function it_belongs_to_peg_asset()
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
        
        $pegAsset = $stablecoin->pegAsset;
        $this->assertInstanceOf(Asset::class, $pegAsset);
        $this->assertEquals('USD', $pegAsset->code);
    }

    /** @test */
    public function it_has_active_positions_relationship()
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
        
        $account = \App\Models\Account::factory()->create();
        
        StablecoinCollateralPosition::create([
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        // Create a different account for the closed position
        $anotherAccount = Account::factory()->create();
        StablecoinCollateralPosition::create([
            'account_uuid' => $anotherAccount->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 0,
            'debt_amount' => 0,
            'collateral_ratio' => 0,
            'status' => 'closed',
        ]);
        
        $activePositions = $stablecoin->activePositions;
        $this->assertCount(1, $activePositions);
    }

    /** @test */
    public function it_can_scope_by_mechanism()
    {
        Stablecoin::create([
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
            'total_supply' => 0,
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
        
        $collateralized = Stablecoin::byMechanism('collateralized')->get();
        $this->assertCount(1, $collateralized);
        $this->assertEquals('CUSD', $collateralized->first()->code);
        
        $algorithmic = Stablecoin::byMechanism('algorithmic')->get();
        $this->assertCount(1, $algorithmic);
        $this->assertEquals('AUSD', $algorithmic->first()->code);
    }

    /** @test */
    public function it_casts_decimal_attributes_correctly()
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
        
        $fresh = Stablecoin::find('FUSD');
        
        // Decimal casts return strings in PHP, not floats
        $this->assertIsString($fresh->peg_ratio);
        $this->assertIsString($fresh->target_price);
        $this->assertIsString($fresh->collateral_ratio);
        $this->assertIsString($fresh->min_collateral_ratio);
        $this->assertIsString($fresh->liquidation_penalty);
        $this->assertIsString($fresh->mint_fee);
        $this->assertIsString($fresh->burn_fee);
        
        // But they should be numeric strings
        $this->assertIsNumeric($fresh->peg_ratio);
        $this->assertIsNumeric($fresh->target_price);
        $this->assertIsNumeric($fresh->collateral_ratio);
        $this->assertIsNumeric($fresh->min_collateral_ratio);
        $this->assertIsNumeric($fresh->liquidation_penalty);
        $this->assertIsNumeric($fresh->mint_fee);
        $this->assertIsNumeric($fresh->burn_fee);
    }
}