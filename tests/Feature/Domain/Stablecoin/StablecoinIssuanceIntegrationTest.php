<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Stablecoin;

use App\Domain\Stablecoin\Services\StablecoinIssuanceService;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Workflow\WorkflowStub;

class StablecoinIssuanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected StablecoinIssuanceService $service;
    protected Account $account;
    protected Stablecoin $stablecoin;
    protected Asset $usdAsset;
    protected Asset $eurAsset;
    protected Asset $fusdAsset;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use fake workflows
        WorkflowStub::fake();
        
        $this->service = app(StablecoinIssuanceService::class);
        
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
        
        $this->fusdAsset = Asset::firstOrCreate(
            ['code' => 'FUSD'],
            [
                'name' => 'FinAegis USD',
                'type' => 'stablecoin',
                'precision' => 8,
                'is_active' => true
            ]
        );
        
        // Create user and account
        $user = User::factory()->create();
        $this->account = Account::factory()->create(['user_uuid' => $user->uuid]);
        
        // Give account some balance
        $this->account->addBalance('USD', 500000); // $5,000
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
            'liquidation_penalty' => 0.05,
            'total_supply' => 0,
            'max_supply' => 10000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.001,
            'burn_fee' => 0.001,
            'precision' => 8,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
    }

    /** @test */
    public function it_can_mint_stablecoins_with_usd_collateral()
    {
        $collateralAmount = 150000; // $1,500
        $mintAmount = 100000; // $1,000
        
        $position = $this->service->mint(
            $this->account,
            'FUSD',
            'USD',
            $collateralAmount,
            $mintAmount
        );
        
        // Assert position created
        $this->assertInstanceOf(StablecoinCollateralPosition::class, $position);
        $this->assertEquals($this->account->uuid, $position->account_uuid);
        $this->assertEquals('FUSD', $position->stablecoin_code);
        $this->assertEquals('USD', $position->collateral_asset_code);
        $this->assertEquals($collateralAmount, $position->collateral_amount);
        $this->assertEquals($mintAmount, $position->debt_amount);
        $this->assertEquals('active', $position->status);
        
        // Assert workflow was dispatched
        WorkflowStub::assertStarted(\App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow::class, function ($workflow, $args) {
            return $args[0] === $this->account->uuid
                && $args[1] === 'FUSD'
                && $args[2] === 'USD'
                && $args[3] === 150000
                && $args[4] === 100000;
        });
        
        // Assert stablecoin stats updated
        $this->stablecoin->refresh();
        $this->assertEquals($mintAmount, $this->stablecoin->total_supply);
        $this->assertEquals($collateralAmount, $this->stablecoin->total_collateral_value);
    }

    /** @test */
    public function it_can_mint_with_different_collateral_asset()
    {
        // Mock EUR to USD exchange rate
        config(['exchange_rates.EUR.USD' => 1.1]); // 1 EUR = 1.1 USD
        
        $collateralAmount = 150000; // €1,500
        $mintAmount = 100000; // $1,000
        
        $position = $this->service->mint(
            $this->account,
            'FUSD',
            'EUR',
            $collateralAmount,
            $mintAmount
        );
        
        // Assert position created with EUR collateral
        $this->assertEquals('EUR', $position->collateral_asset_code);
        $this->assertEquals($collateralAmount, $position->collateral_amount);
        
        // Assert workflow was dispatched
        WorkflowStub::assertStarted(\App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow::class);
        
        // Assert stablecoin stats updated with converted value
        $this->stablecoin->refresh();
        $this->assertEquals($mintAmount, $this->stablecoin->total_supply);
        // EUR collateral should be converted to USD value for total_collateral_value
        $this->assertEquals(165000, $this->stablecoin->total_collateral_value); // €1,500 * 1.1 = $1,650
    }

    /** @test */
    public function it_can_burn_stablecoins_and_release_collateral()
    {
        // First mint some stablecoins
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
        
        // Update stablecoin stats
        $this->stablecoin->update([
            'total_supply' => 100000,
            'total_collateral_value' => 150000,
        ]);
        
        $burnAmount = 50000; // Burn half
        
        $result = $this->service->burn(
            $this->account,
            'FUSD',
            $burnAmount
        );
        
        // Assert position updated
        $this->assertEquals(75000, $result->collateral_amount); // Half collateral released
        $this->assertEquals(50000, $result->debt_amount); // Half debt remaining
        $this->assertEquals('active', $result->status);
        
        // Assert workflow was dispatched
        WorkflowStub::assertStarted(\App\Domain\Stablecoin\Workflows\BurnStablecoinWorkflow::class, function ($workflow, $args) {
            return $args[0] === $this->account->uuid
                && $args[1] === 'FUSD'
                && $args[2] === 50000;
        });
        
        // Assert stablecoin stats updated
        $this->stablecoin->refresh();
        $this->assertEquals(50000, $this->stablecoin->total_supply);
        $this->assertEquals(75000, $this->stablecoin->total_collateral_value);
    }

    /** @test */
    public function it_can_burn_entire_position()
    {
        // Create position
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
        
        // Update stablecoin stats
        $this->stablecoin->update([
            'total_supply' => 100000,
            'total_collateral_value' => 150000,
        ]);
        
        $result = $this->service->burn(
            $this->account,
            'FUSD',
            100000 // Burn all
        );
        
        // Assert position closed
        $this->assertEquals(0, $result->collateral_amount);
        $this->assertEquals(0, $result->debt_amount);
        $this->assertEquals('closed', $result->status);
        
        // Assert workflow was dispatched
        WorkflowStub::assertStarted(\App\Domain\Stablecoin\Workflows\BurnStablecoinWorkflow::class);
        
        // Assert stablecoin stats updated
        $this->stablecoin->refresh();
        $this->assertEquals(0, $this->stablecoin->total_supply);
        $this->assertEquals(0, $this->stablecoin->total_collateral_value);
    }

    /** @test */
    public function it_can_add_collateral_to_existing_position()
    {
        // Create position
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        $this->stablecoin->update([
            'total_supply' => 100000,
            'total_collateral_value' => 150000,
        ]);
        
        $additionalCollateral = 50000;
        
        $result = $this->service->addCollateral(
            $this->account,
            'FUSD',
            'USD',
            $additionalCollateral
        );
        
        // Assert position updated
        $this->assertEquals(200000, $result->collateral_amount);
        $this->assertEquals(100000, $result->debt_amount); // Debt unchanged
        
        // Assert workflow was dispatched
        WorkflowStub::assertStarted(\App\Domain\Stablecoin\Workflows\AddCollateralWorkflow::class, function ($workflow, $args) {
            return $args[0] === $this->account->uuid
                && $args[1] === 'FUSD'
                && $args[2] === 'USD'
                && $args[3] === 50000;
        });
        
        // Assert stablecoin stats updated
        $this->stablecoin->refresh();
        $this->assertEquals(200000, $this->stablecoin->total_collateral_value);
    }

    /** @test */
    public function it_updates_existing_position_when_minting_again()
    {
        // Create existing position
        $existingPosition = StablecoinCollateralPosition::create([
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'status' => 'active',
        ]);
        
        $this->stablecoin->update([
            'total_supply' => 100000,
            'total_collateral_value' => 150000,
        ]);
        
        $additionalCollateral = 75000;
        $additionalMint = 50000;
        
        $position = $this->service->mint(
            $this->account,
            'FUSD',
            'USD',
            $additionalCollateral,
            $additionalMint
        );
        
        // Assert same position updated
        $this->assertEquals($existingPosition->uuid, $position->uuid);
        $this->assertEquals(225000, $position->collateral_amount);
        $this->assertEquals(150000, $position->debt_amount);
        
        // Assert workflow was dispatched with existing position UUID
        WorkflowStub::assertStarted(\App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow::class);
        
        // Assert stablecoin stats updated
        $this->stablecoin->refresh();
        $this->assertEquals(150000, $this->stablecoin->total_supply);
        $this->assertEquals(225000, $this->stablecoin->total_collateral_value);
    }

    /** @test */
    public function it_prevents_minting_when_disabled()
    {
        $this->stablecoin->update(['minting_enabled' => false]);
        
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
        $this->stablecoin->update(['total_supply' => 10000000]);
        
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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient collateral ratio. Required: 150%, Provided: 100%');
        
        $this->service->mint(
            $this->account,
            'FUSD',
            'USD',
            100000, // Only $1,000 collateral
            100000  // Want $1,000 stablecoin (only 100% ratio)
        );
    }

    /** @test */
    public function it_validates_account_balance()
    {
        // Create account with no balance
        $poorAccount = Account::factory()->create();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient USD balance for collateral');
        
        $this->service->mint(
            $poorAccount,
            'FUSD',
            'USD',
            150000,
            100000
        );
    }

    /** @test */
    public function it_prevents_burning_when_disabled()
    {
        $this->stablecoin->update(['burning_enabled' => false]);
        
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
        $this->expectExceptionMessage('Burning is disabled for FUSD');
        
        $this->service->burn($this->account, 'FUSD', 50000);
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
        
        $this->service->burn($this->account, 'FUSD', 150000); // Try to burn more than debt
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
        $this->expectExceptionMessage('Collateral asset mismatch. Position uses USD, trying to add EUR');
        
        $this->service->addCollateral($this->account, 'FUSD', 'EUR', 50000);
    }
}