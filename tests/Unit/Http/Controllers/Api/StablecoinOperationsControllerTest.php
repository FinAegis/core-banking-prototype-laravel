<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Stablecoin\Services\LiquidationService;
use App\Domain\Stablecoin\Services\StablecoinIssuanceService;
use App\Http\Controllers\Api\StablecoinOperationsController;
use App\Models\Account;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use App\Domain\Asset\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class StablecoinOperationsControllerTest extends TestCase
{
    protected function shouldCreateDefaultAccountsInSetup(): bool
    {
        return false;
    }

    protected StablecoinOperationsController $controller;
    protected $issuanceService;
    protected $collateralService;
    protected $liquidationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->issuanceService = Mockery::mock(StablecoinIssuanceService::class);
        $this->collateralService = Mockery::mock(CollateralService::class);
        $this->liquidationService = Mockery::mock(LiquidationService::class);
        
        $this->controller = new StablecoinOperationsController(
            $this->issuanceService,
            $this->collateralService,
            $this->liquidationService
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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_mint_stablecoins()
    {
        $account = Account::factory()->create();
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
        
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        $this->issuanceService
            ->shouldReceive('mint')
            ->once()
            ->with(
                Mockery::type(Account::class),
                'FUSD',
                'USD',
                150000,
                100000
            )
            ->andReturn($position);
            
        $request = Request::create('/api/v2/stablecoin-operations/mint', 'POST', [
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'mint_amount' => 100000,
        ]);
        
        $response = $this->controller->mint($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Stablecoin minted successfully', $data['message']);
        $this->assertEquals($position->uuid, $data['data']['uuid']);
    }

    /** @test */
    public function it_handles_mint_errors()
    {
        $account = Account::factory()->create();
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
        
        $this->issuanceService
            ->shouldReceive('mint')
            ->once()
            ->andThrow(new \RuntimeException('Insufficient collateral'));
            
        $request = Request::create('/api/v2/stablecoin-operations/mint', 'POST', [
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 100000,
            'mint_amount' => 100000,
        ]);
        
        $response = $this->controller->mint($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Insufficient collateral', $data['error']);
    }

    /** @test */
    public function it_can_burn_stablecoins()
    {
        $account = Account::factory()->create();
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
        
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 75000,
            'debt_amount' => 50000,
            'collateral_ratio' => 1.5,
            'status' => 'active',
        ]);
        
        $this->issuanceService
            ->shouldReceive('burn')
            ->once()
            ->with(
                Mockery::type(Account::class),
                'FUSD',
                50000,
                null
            )
            ->andReturn($position);
            
        $request = Request::create('/api/v2/stablecoin-operations/burn', 'POST', [
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'FUSD',
            'burn_amount' => 50000,
        ]);
        
        $response = $this->controller->burn($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Stablecoin burned successfully', $data['message']);
    }

    /** @test */
    public function it_can_add_collateral()
    {
        $account = Account::factory()->create();
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 200000,
            'debt_amount' => 100000,
            'collateral_ratio' => 2.0,
            'status' => 'active',
        ]);
        
        $this->issuanceService
            ->shouldReceive('addCollateral')
            ->once()
            ->with(
                Mockery::type(Account::class),
                'FUSD',
                50000
            )
            ->andReturn($position);
            
        $request = Request::create('/api/v2/stablecoin-operations/add-collateral', 'POST', [
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'FUSD',
            'additional_collateral' => 50000,
        ]);
        
        $response = $this->controller->addCollateral($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Collateral added successfully', $data['message']);
    }

    /** @test */
    public function it_can_get_liquidation_opportunities()
    {
        $positions = collect([
            StablecoinCollateralPosition::make([
                'uuid' => 'test-uuid-1',
                'account_uuid' => 'account-1',
                'stablecoin_code' => 'FUSD',
                'collateral_asset_code' => 'USD',
                'collateral_amount' => 120000,
                'debt_amount' => 100000,
                'collateral_ratio' => 1.2,
                'liquidation_price' => 1.2,
                'status' => 'active',
            ]),
        ]);
        
        $this->liquidationService
            ->shouldReceive('getLiquidationOpportunities')
            ->once()
            ->andReturn($positions);
            
        $request = Request::create('/api/v2/stablecoin-operations/liquidation/opportunities', 'GET');
        
        $response = $this->controller->getLiquidationOpportunities($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']);
    }

    /** @test */
    public function it_can_liquidate_position()
    {
        $account = Account::factory()->create();
        $position = StablecoinCollateralPosition::create([
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 120000,
            'debt_amount' => 100000,
            'collateral_ratio' => 1.2,
            'status' => 'liquidated',
        ]);
        
        $liquidationResult = [
            'position' => $position,
            'collateral_seized' => 120000,
            'debt_repaid' => 100000,
            'penalty_amount' => 10000,
            'remainder_to_owner' => 10000,
        ];
        
        $this->liquidationService
            ->shouldReceive('liquidatePosition')
            ->once()
            ->with($position->uuid)
            ->andReturn($liquidationResult);
            
        $request = Request::create('/api/v2/stablecoin-operations/liquidate', 'POST', [
            'position_uuid' => $position->uuid,
        ]);
        
        $response = $this->controller->liquidate($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Position liquidated successfully', $data['message']);
    }

    /** @test */
    public function it_can_get_account_positions()
    {
        $account = Account::factory()->create();
        
        $positions = collect([
            StablecoinCollateralPosition::make([
                'uuid' => 'test-uuid-1',
                'account_uuid' => $account->uuid,
                'stablecoin_code' => 'FUSD',
                'collateral_asset_code' => 'USD',
                'collateral_amount' => 150000,
                'debt_amount' => 100000,
                'collateral_ratio' => 1.5,
                'status' => 'active',
            ]),
        ]);
        
        StablecoinCollateralPosition::shouldReceive('where->where->with->get')
            ->andReturn($positions);
            
        $request = Request::create("/api/v2/stablecoin-operations/positions/{$account->uuid}", 'GET');
        
        $response = $this->controller->getAccountPositions($request, $account->uuid);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_can_get_positions_at_risk()
    {
        $positions = collect([
            StablecoinCollateralPosition::make([
                'uuid' => 'test-uuid-1',
                'account_uuid' => 'account-1',
                'stablecoin_code' => 'FUSD',
                'collateral_asset_code' => 'USD',
                'collateral_amount' => 125000,
                'debt_amount' => 100000,
                'collateral_ratio' => 1.25,
                'status' => 'active',
                'health_score' => 0.2,
            ]),
        ]);
        
        $this->collateralService
            ->shouldReceive('getPositionsAtRisk')
            ->once()
            ->andReturn($positions);
            
        $this->collateralService
            ->shouldReceive('calculatePositionHealthScore')
            ->once()
            ->andReturn(0.2);
            
        $request = Request::create('/api/v2/stablecoin-operations/positions/at-risk', 'GET');
        
        $response = $this->controller->getPositionsAtRisk($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']);
    }

    /** @test */
    public function it_handles_validation_errors()
    {
        $request = Request::create('/api/v2/stablecoin-operations/mint', 'POST', [
            'account_uuid' => 'invalid-uuid',
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'mint_amount' => 100000,
        ]);
        
        $response = $this->controller->mint($request);
        
        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function it_handles_invalid_burn_amount()
    {
        $account = Account::factory()->create();
        
        $this->issuanceService
            ->shouldReceive('burn')
            ->once()
            ->andThrow(new \RuntimeException('Cannot burn more than debt amount'));
            
        $request = Request::create('/api/v2/stablecoin-operations/burn', 'POST', [
            'account_uuid' => $account->uuid,
            'stablecoin_code' => 'FUSD',
            'burn_amount' => 200000, // More than debt
        ]);
        
        $response = $this->controller->burn($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Cannot burn more than debt amount', $data['error']);
    }

    /** @test */
    public function it_can_handle_empty_positions_list()
    {
        $this->liquidationService
            ->shouldReceive('getLiquidationOpportunities')
            ->once()
            ->andReturn(collect());
            
        $request = Request::create('/api/v2/stablecoin-operations/liquidation/opportunities', 'GET');
        
        $response = $this->controller->getLiquidationOpportunities($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['data']);
    }
}