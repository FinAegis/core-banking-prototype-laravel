<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Stablecoin\Services\StablecoinIssuanceService;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Models\Account;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use App\Domain\Asset\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StablecoinIssuanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StablecoinIssuanceService $service;
    protected $exchangeRateService;
    protected $collateralService;
    protected Account $account;
    protected Stablecoin $stablecoin;
    protected Asset $usdAsset;
    protected Asset $eurAsset;

    protected function setUp(): void
    {
        // Call grandparent setUp to avoid TestCase creating unnecessary data
        \Illuminate\Foundation\Testing\TestCase::setUp();
        
        $this->exchangeRateService = Mockery::mock(ExchangeRateService::class);
        $this->collateralService = Mockery::mock(CollateralService::class);
        $this->service = new StablecoinIssuanceService(
            $this->exchangeRateService,
            $this->collateralService
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
        
        $this->eurAsset = Asset::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true
            ]
        );
        
        // Create account with balances
        $this->account = Account::factory()->create();
        $this->account->addBalance('USD', 1000000); // $10,000
        $this->account->addBalance('EUR', 500000); // €5,000
        
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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_mint_stablecoins_with_usd_collateral()
    {
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 150000, 'USD')
            ->once()
            ->andReturn(150000);
            
        $position = $this->service->mint(
            $this->account,
            'FUSD',
            'USD',
            150000, // $1,500 collateral
            100000  // $1,000 mint
        );
        
        $this->assertInstanceOf(StablecoinCollateralPosition::class, $position);
        $this->assertEquals(150000, $position->collateral_amount);
        $this->assertEquals(100000, $position->debt_amount);
        $this->assertEquals('active', $position->status);
        
        // Check account balances
        $this->assertEquals(850000, $this->account->getBalance('USD')); // Collateral locked
        $this->assertEquals(99500, $this->account->getBalance('FUSD')); // Minted minus fee (0.5%)
        
        // Check stablecoin stats
        $this->stablecoin->refresh();
        $this->assertEquals(100000, $this->stablecoin->total_supply);
        $this->assertEquals(150000, $this->stablecoin->total_collateral_value);
    }

    /** @test */
    public function it_can_mint_with_different_collateral_asset()
    {
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('EUR', 150000, 'USD')
            ->twice() // Once for validation, once for stats
            ->andReturn(165000); // EUR worth more than USD
            
        $position = $this->service->mint(
            $this->account,
            'FUSD',
            'EUR',
            150000, // €1,500 collateral
            100000  // $1,000 mint
        );
        
        $this->assertEquals('EUR', $position->collateral_asset_code);
        $this->assertEquals(150000, $position->collateral_amount);
        $this->assertEquals(100000, $position->debt_amount);
        
        // Check EUR was deducted
        $this->assertEquals(350000, $this->account->getBalance('EUR'));
        $this->assertEquals(99500, $this->account->getBalance('FUSD'));
        
        // Check stablecoin stats reflect converted value
        $this->stablecoin->refresh();
        $this->assertEquals(165000, $this->stablecoin->total_collateral_value);
    }

    /** @test */
    public function it_prevents_minting_when_disabled()
    {
        $this->stablecoin->minting_enabled = false;
        $this->stablecoin->save();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Minting is disabled for FUSD');
        
        $this->service->mint(
            $this->account,
            'FUSD',
            'USD',
            150000,
            100000
        );
    }

    /** @test */
    public function it_prevents_minting_when_max_supply_reached()
    {
        $this->stablecoin->total_supply = 10000000; // Max supply
        $this->stablecoin->save();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Maximum supply reached for FUSD');
        
        $this->service->mint(
            $this->account,
            'FUSD',
            'USD',
            150000,
            100000
        );
    }

    /** @test */
    public function it_validates_collateral_sufficiency()
    {
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 100000, 'USD')
            ->once()
            ->andReturn(100000);
            
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient collateral. Required ratio: 1.5, provided ratio: 1');
        
        $this->service->mint(
            $this->account,
            'FUSD',
            'USD',
            100000, // Only 1:1 ratio, need 1.5:1
            100000
        );
    }

    /** @test */
    public function it_validates_account_balance()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient USD balance for collateral');
        
        $this->service->mint(
            $this->account,
            'FUSD',
            'USD',
            2000000, // $20,000 - more than account has
            1000000
        );
    }

    /** @test */
    public function it_can_burn_stablecoins_and_release_collateral()
    {
        // Create a position first
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        // Give account FUSD to burn
        $this->account->addBalance('FUSD', 100000);
        
        // Set up stablecoin stats
        $this->stablecoin->total_supply = 100000;
        $this->stablecoin->total_collateral_value = 150000;
        $this->stablecoin->save();
        
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 75000, 'USD')
            ->once()
            ->andReturn(75000);
            
        $updatedPosition = $this->service->burn(
            $this->account,
            'FUSD',
            50000, // Burn $500
            null   // Auto-calculate collateral release
        );
        
        // Check position was updated
        $this->assertEquals(50000, $updatedPosition->debt_amount);
        $this->assertEquals(75000, $updatedPosition->collateral_amount);
        $this->assertEquals('active', $updatedPosition->status);
        
        // Check balances
        $this->assertEquals(49850, $this->account->getBalance('FUSD')); // 100000 - 50000 - (50000 * 0.003)
        $this->assertEquals(1075000, $this->account->getBalance('USD')); // Original + released
        
        // Check stablecoin stats
        $this->stablecoin->refresh();
        $this->assertEquals(50000, $this->stablecoin->total_supply);
        $this->assertEquals(75000, $this->stablecoin->total_collateral_value);
    }

    /** @test */
    public function it_can_burn_entire_position()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        $this->account->addBalance('FUSD', 100000);
        $this->stablecoin->total_supply = 100000;
        $this->stablecoin->total_collateral_value = 150000;
        $this->stablecoin->save();
        
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 150000, 'USD')
            ->once()
            ->andReturn(150000);
            
        $updatedPosition = $this->service->burn(
            $this->account,
            'FUSD',
            100000 // Burn entire debt
        );
        
        // Position should be closed
        $this->assertEquals(0, $updatedPosition->debt_amount);
        $this->assertEquals(0, $updatedPosition->collateral_amount);
        $this->assertEquals('closed', $updatedPosition->status);
        
        // All collateral should be returned
        $this->assertEquals(1150000, $this->account->getBalance('USD'));
    }

    /** @test */
    public function it_prevents_burning_when_disabled()
    {
        $this->stablecoin->burning_enabled = false;
        $this->stablecoin->save();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Burning is disabled for FUSD');
        
        $this->service->burn(
            $this->account,
            'FUSD',
            50000
        );
    }

    /** @test */
    public function it_validates_burn_amount()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot burn more than debt amount');
        
        $this->service->burn(
            $this->account,
            'FUSD',
            150000 // More than debt
        );
    }

    /** @test */
    public function it_prevents_burn_creating_undercollateralized_position()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        $this->account->addBalance('FUSD', 100000);
        
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 10000, 'USD')
            ->once()
            ->andReturn(10000);
            
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Collateral release would make position undercollateralized');
        
        $this->service->burn(
            $this->account,
            'FUSD',
            10000,  // Burn $100
            140000  // Try to release too much collateral
        );
    }

    /** @test */
    public function it_can_add_collateral_to_existing_position()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 120000, // At minimum ratio
            'debt_amount' => 100000,
            'collateral_ratio' => 1.2,
            'status' => 'active',
        ]);
        
        $this->stablecoin->total_collateral_value = 120000;
        $this->stablecoin->save();
        
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 30000, 'USD')
            ->once()
            ->andReturn(30000);
            
        $updatedPosition = $this->service->addCollateral(
            $this->account,
            'FUSD',
            'USD',
            30000 // Add $300
        );
        
        $this->assertEquals(150000, $updatedPosition->collateral_amount);
        $this->assertEquals(1.5, $updatedPosition->collateral_ratio);
        
        // Check account balance
        $this->assertEquals(970000, $this->account->getBalance('USD'));
        
        // Check stablecoin stats
        $this->stablecoin->refresh();
        $this->assertEquals(150000, $this->stablecoin->total_collateral_value);
    }

    /** @test */
    public function it_validates_collateral_asset_match()
    {
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Collateral asset mismatch');
        
        $this->service->addCollateral(
            $this->account,
            'FUSD',
            'EUR', // Different asset
            30000
        );
    }

    /** @test */
    public function it_updates_existing_position_when_minting_again()
    {
        // Create initial position
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 150000, 'USD')
            ->once()
            ->andReturn(150000);
            
        // Mint more to existing position
        $updatedPosition = $this->service->mint(
            $this->account,
            'FUSD',
            'USD',
            150000, // Add $1,500 more collateral
            100000  // Mint $1,000 more
        );
        
        // Should have updated the same position
        $this->assertEquals($position->uuid, $updatedPosition->uuid);
        $this->assertEquals(300000, $updatedPosition->collateral_amount);
        $this->assertEquals(200000, $updatedPosition->debt_amount);
    }
}