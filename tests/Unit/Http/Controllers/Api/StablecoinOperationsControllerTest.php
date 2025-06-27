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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mockery;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Str;

class StablecoinOperationsControllerTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

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
        
        // Mock the validator to bypass database checks
        Validator::shouldReceive('make')->andReturnUsing(function ($data, $rules) {
            $validator = Mockery::mock(\Illuminate\Contracts\Validation\Validator::class);
            $validator->shouldReceive('validate')->andReturn($data);
            $validator->shouldReceive('fails')->andReturn(false);
            $validator->shouldReceive('validated')->andReturn($data);
            return $validator;
        });
        
        // Mock Account model static methods
        Account::shouldReceive('where->firstOrFail')->andReturnUsing(function () {
            $account = new \stdClass();
            $account->uuid = (string) Str::uuid();
            return $account;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_mint_stablecoins()
    {
        $position = Mockery::mock(StablecoinCollateralPosition::class);
        $position->uuid = (string) Str::uuid();
        $position->shouldReceive('load')->andReturn($position);
        
        $this->issuanceService
            ->shouldReceive('mint')
            ->once()
            ->andReturn($position);
            
        $request = Request::create('/api/v2/stablecoin-operations/mint', 'POST', [
            'account_uuid' => (string) Str::uuid(),
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 150000,
            'mint_amount' => 100000,
        ]);
        
        $response = $this->controller->mint($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Stablecoin minted successfully', $data['message']);
    }

    /** @test */
    public function it_handles_mint_errors()
    {
        $this->issuanceService
            ->shouldReceive('mint')
            ->once()
            ->andThrow(new \RuntimeException('Insufficient collateral'));
            
        $request = Request::create('/api/v2/stablecoin-operations/mint', 'POST', [
            'account_uuid' => (string) Str::uuid(),
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
        $position = Mockery::mock(StablecoinCollateralPosition::class);
        $position->shouldReceive('load')->andReturn($position);
        
        $this->issuanceService
            ->shouldReceive('burn')
            ->once()
            ->andReturn($position);
            
        $request = Request::create('/api/v2/stablecoin-operations/burn', 'POST', [
            'account_uuid' => (string) Str::uuid(),
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
        $position = Mockery::mock(StablecoinCollateralPosition::class);
        $position->shouldReceive('load')->andReturn($position);
        
        $this->issuanceService
            ->shouldReceive('addCollateral')
            ->once()
            ->andReturn($position);
            
        $request = Request::create('/api/v2/stablecoin-operations/add-collateral', 'POST', [
            'account_uuid' => (string) Str::uuid(),
            'stablecoin_code' => 'FUSD',
            'collateral_asset_code' => 'USD',
            'collateral_amount' => 50000,
        ]);
        
        $response = $this->controller->addCollateral($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Collateral added successfully', $data['message']);
    }

    /** @test */
    public function it_can_get_liquidation_opportunities()
    {
        $opportunities = collect([
            [
                'position_uuid' => (string) Str::uuid(),
                'account_uuid' => (string) Str::uuid(),
                'stablecoin_code' => 'FUSD',
                'eligible' => true,
                'reward' => 5000,
                'penalty' => 10000,
                'collateral_seized' => 100000,
                'debt_amount' => 90000,
                'collateral_asset' => 'USD',
                'current_ratio' => '1.1000',
                'min_ratio' => '1.2000',
                'priority_score' => 0.85,
                'health_score' => 0.1,
            ],
        ]);
        
        $this->liquidationService
            ->shouldReceive('getLiquidationOpportunities')
            ->once()
            ->with(50)
            ->andReturn($opportunities);
            
        $request = Request::create('/api/v2/stablecoin-operations/liquidation-opportunities', 'GET');
        
        $response = $this->controller->getLiquidationOpportunities($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']);
    }

    /** @test */
    public function it_handles_burn_errors()
    {
        $this->issuanceService
            ->shouldReceive('burn')
            ->once()
            ->andThrow(new \RuntimeException('Cannot burn more than debt amount'));
            
        $request = Request::create('/api/v2/stablecoin-operations/burn', 'POST', [
            'account_uuid' => (string) Str::uuid(),
            'stablecoin_code' => 'FUSD',
            'burn_amount' => 200000,
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
            ->with(50)
            ->andReturn(collect());
            
        $request = Request::create('/api/v2/stablecoin-operations/liquidation-opportunities', 'GET');
        
        $response = $this->controller->getLiquidationOpportunities($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['data']);
    }
}