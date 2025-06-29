<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Stablecoin\Services\StablecoinIssuanceService;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Wallet\Services\WalletService;
use App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow;
use App\Domain\Stablecoin\Workflows\BurnStablecoinWorkflow;
use App\Domain\Stablecoin\Workflows\AddCollateralWorkflow;
use App\Models\Account;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use App\Domain\Asset\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Workflow\WorkflowStub;

class StablecoinIssuanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StablecoinIssuanceService $service;
    protected $exchangeRateService;
    protected $collateralService;
    protected $walletService;
    protected Account $account;
    protected Stablecoin $stablecoin;
    protected Asset $usdAsset;
    protected Asset $eurAsset;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->exchangeRateService = Mockery::mock(ExchangeRateService::class);
        $this->collateralService = Mockery::mock(CollateralService::class);
        $this->walletService = Mockery::mock(WalletService::class);
        
        $this->service = new StablecoinIssuanceService(
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
        
        $this->eurAsset = Asset::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true
            ]
        );
        
        // Create account
        $this->account = Account::factory()->create();
        
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_mint_stablecoins_with_usd_collateral()
    {
        // This test has been moved to integration tests
        $this->markTestSkipped('Moved to StablecoinIssuanceIntegrationTest');
    }

    /** @test */
    public function it_can_mint_with_different_collateral_asset()
    {
        // This test has been moved to integration tests
        $this->markTestSkipped('Moved to StablecoinIssuanceIntegrationTest');
        return;
        // Setup
        $positionUuid = (string) Str::uuid();
        $collateralAmount = 150000; // €1,500
        $mintAmount = 100000; // $1,000
        $eurValueInUsd = 165000; // EUR worth more
        
        // Mock account balance check
        $accountMock = Mockery::mock($this->account);
        $accountMock->shouldReceive('hasSufficientBalance')
            ->with('EUR', $collateralAmount)
            ->once()
            ->andReturn(true);
        $accountMock->uuid = $this->account->uuid;
        
        // Mock collateral service
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('EUR', $collateralAmount, 'USD')
            ->once()
            ->andReturn($eurValueInUsd);
        
        // Mock workflow execution
        $workflowStub = Mockery::mock(WorkflowStub::class);
        $workflowStub->shouldReceive('start')->once()->andReturnSelf();
        $workflowStub->shouldReceive('await')->once()->andReturn($positionUuid);
        
        $this->mockWorkflowMake(MintStablecoinWorkflow::class, $workflowStub);
        
        // Create position
        StablecoinCollateralPosition::create([
            'uuid' => $positionUuid,
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'EUR',
            'collateral_amount' => $collateralAmount,
            'debt_amount' => $mintAmount,
            'collateral_ratio' => 1.65,
            'status' => 'active',
        ]);
        
        // Execute
        $position = $this->service->mint(
            $accountMock,
            'FUSD',
            'EUR',
            $collateralAmount,
            $mintAmount
        );
        
        // Assert
        $this->assertEquals('EUR', $position->collateral_asset_code);
        $this->assertEquals($collateralAmount, $position->collateral_amount);
        
        // Check stablecoin stats
        $this->stablecoin->refresh();
        $this->assertEquals($eurValueInUsd, $this->stablecoin->total_collateral_value);
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
        // Mock insufficient collateral value
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 100000, 'USD')
            ->once()
            ->andReturn(100000); // Only 1:1 ratio, need 1.5:1
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient collateral');
        
        $this->service->mint(
            $this->account,
            'FUSD',
            'USD',
            100000, // Only $1,000 collateral
            100000  // Want $1,000 stablecoin
        );
    }

    /** @test */
    public function it_validates_account_balance()
    {
        // Mock insufficient balance
        $accountMock = Mockery::mock($this->account);
        $accountMock->shouldReceive('hasSufficientBalance')
            ->with('USD', 150000)
            ->once()
            ->andReturn(false);
        $accountMock->uuid = $this->account->uuid;
        
        // Mock collateral service for validation check
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 150000, 'USD')
            ->once()
            ->andReturn(150000);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient USD balance for collateral');
        
        $this->service->mint(
            $accountMock,
            'FUSD',
            'USD',
            150000,
            100000
        );
    }

    /** @test */
    public function it_can_burn_stablecoins_and_release_collateral()
    {
        // This test has been moved to integration tests
        $this->markTestSkipped('Moved to StablecoinIssuanceIntegrationTest');
        return;
        // Setup
        $position = StablecoinCollateralPosition::create([
            'uuid' => (string) Str::uuid(),
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        $burnAmount = 50000; // Burn half
        $collateralRelease = 75000; // Release proportional collateral
        
        // Mock account balance check
        $accountMock = Mockery::mock($this->account);
        $accountMock->shouldReceive('hasSufficientBalance')
            ->with('FUSD', $burnAmount)
            ->once()
            ->andReturn(true);
        $accountMock->uuid = $this->account->uuid;
        
        // Mock collateral service for validation
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 75000, 'USD') // Remaining collateral
            ->once()
            ->andReturn(75000);
        
        // Mock workflow execution
        $workflowStub = Mockery::mock(WorkflowStub::class);
        $workflowStub->shouldReceive('start')->once()->andReturnSelf();
        $workflowStub->shouldReceive('await')->once()->andReturn(true);
        
        $this->mockWorkflowMake(BurnStablecoinWorkflow::class, $workflowStub);
        
        // Update position to reflect burn
        $position->update([
            'collateral_amount' => 75000,
            'debt_amount' => 50000,
        ]);
        
        // Execute
        $result = $this->service->burn(
            $accountMock,
            'FUSD',
            $burnAmount
        );
        
        // Assert
        $this->assertEquals(75000, $result->collateral_amount);
        $this->assertEquals(50000, $result->debt_amount);
        $this->assertEquals('active', $result->status);
    }

    /** @test */
    public function it_can_burn_entire_position()
    {
        // This test has been moved to integration tests
        $this->markTestSkipped('Moved to StablecoinIssuanceIntegrationTest');
        return;
        // Setup
        $position = StablecoinCollateralPosition::create([
            'uuid' => (string) Str::uuid(),
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        // Mock account balance check
        $accountMock = Mockery::mock($this->account);
        $accountMock->shouldReceive('hasSufficientBalance')
            ->with('FUSD', 100000)
            ->once()
            ->andReturn(true);
        $accountMock->uuid = $this->account->uuid;
        
        // Mock workflow execution
        $workflowStub = Mockery::mock(WorkflowStub::class);
        $workflowStub->shouldReceive('start')->once()->andReturnSelf();
        $workflowStub->shouldReceive('await')->once()->andReturn(true);
        
        $this->mockWorkflowMake(BurnStablecoinWorkflow::class, $workflowStub);
        
        // Update position to reflect complete burn
        $position->update([
            'collateral_amount' => 0,
            'debt_amount' => 0,
            'status' => 'closed',
        ]);
        
        // Execute
        $result = $this->service->burn(
            $accountMock,
            'FUSD',
            100000 // Burn all
        );
        
        // Assert
        $this->assertEquals(0, $result->collateral_amount);
        $this->assertEquals(0, $result->debt_amount);
        $this->assertEquals('closed', $result->status);
    }

    /** @test */
    public function it_prevents_burning_when_disabled()
    {
        $this->stablecoin->update(['burning_enabled' => false]);
        
        $position = StablecoinCollateralPosition::create([
            'uuid' => (string) Str::uuid(),
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
            'uuid' => (string) Str::uuid(),
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
    public function it_prevents_burn_creating_undercollateralized_position()
    {
        $position = StablecoinCollateralPosition::create([
            'uuid' => (string) Str::uuid(),
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        // Mock collateral service - remaining position would be undercollateralized
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', 10000, 'USD') // Very little collateral left
            ->once()
            ->andReturn(10000);
        
        $accountMock = Mockery::mock($this->account);
        $accountMock->shouldReceive('hasSufficientBalance')->andReturn(true);
        $accountMock->uuid = $this->account->uuid;
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Collateral release would make position undercollateralized');
        
        $this->service->burn($accountMock, 'FUSD', 10000, 140000); // Try to release too much collateral
    }

    /** @test */
    public function it_can_add_collateral_to_existing_position()
    {
        // This test has been moved to integration tests
        $this->markTestSkipped('Moved to StablecoinIssuanceIntegrationTest');
        return;
        // Setup
        $position = StablecoinCollateralPosition::create([
            'uuid' => (string) Str::uuid(),
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        $position->stablecoin()->associate($this->stablecoin);
        
        $additionalCollateral = 50000;
        
        // Mock account balance check
        $accountMock = Mockery::mock($this->account);
        $accountMock->shouldReceive('hasSufficientBalance')
            ->with('USD', $additionalCollateral)
            ->once()
            ->andReturn(true);
        $accountMock->uuid = $this->account->uuid;
        
        // Mock collateral service
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', $additionalCollateral, 'USD')
            ->once()
            ->andReturn($additionalCollateral);
        
        // Mock workflow execution
        $workflowStub = Mockery::mock(WorkflowStub::class);
        $workflowStub->shouldReceive('start')->once()->andReturnSelf();
        $workflowStub->shouldReceive('await')->once()->andReturn(true);
        
        $this->mockWorkflowMake(AddCollateralWorkflow::class, $workflowStub);
        
        // Update position
        $position->update(['collateral_amount' => 200000]);
        
        // Execute
        $result = $this->service->addCollateral(
            $accountMock,
            'FUSD',
            'USD',
            $additionalCollateral
        );
        
        // Assert
        $this->assertEquals(200000, $result->collateral_amount);
        $this->assertEquals(100000, $result->debt_amount); // Debt unchanged
    }

    /** @test */
    public function it_validates_collateral_asset_match()
    {
        $position = StablecoinCollateralPosition::create([
            'uuid' => (string) Str::uuid(),
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
        
        $this->service->addCollateral($this->account, 'FUSD', 'EUR', 50000);
    }

    /** @test */
    public function it_updates_existing_position_when_minting_again()
    {
        // This test has been moved to integration tests
        $this->markTestSkipped('Moved to StablecoinIssuanceIntegrationTest');
        return;
        // Setup existing position
        $existingPosition = StablecoinCollateralPosition::create([
            'uuid' => (string) Str::uuid(),
            'account_uuid' => $this->account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'status' => 'active',
        ]);
        
        $additionalCollateral = 75000;
        $additionalMint = 50000;
        
        // Mock account balance check
        $accountMock = Mockery::mock($this->account);
        $accountMock->shouldReceive('hasSufficientBalance')
            ->with('USD', $additionalCollateral)
            ->once()
            ->andReturn(true);
        $accountMock->uuid = $this->account->uuid;
        
        // Mock collateral service
        $this->collateralService
            ->shouldReceive('convertToPegAsset')
            ->with('USD', $additionalCollateral, 'USD')
            ->once()
            ->andReturn($additionalCollateral);
        
        // Mock workflow execution - should use existing position UUID
        $workflowStub = Mockery::mock(WorkflowStub::class);
        $workflowStub->shouldReceive('start')->once()->andReturnSelf();
        $workflowStub->shouldReceive('await')->once()->andReturn($existingPosition->uuid);
        
        $this->mockWorkflowMake(MintStablecoinWorkflow::class, $workflowStub);
        
        // Update position
        $existingPosition->update([
            'collateral_amount' => 225000,
            'debt_amount' => 150000,
        ]);
        
        // Execute
        $position = $this->service->mint(
            $accountMock,
            'FUSD',
            'USD',
            $additionalCollateral,
            $additionalMint
        );
        
        // Assert
        $this->assertEquals($existingPosition->uuid, $position->uuid);
        $this->assertEquals(225000, $position->collateral_amount);
        $this->assertEquals(150000, $position->debt_amount);
    }
    
}