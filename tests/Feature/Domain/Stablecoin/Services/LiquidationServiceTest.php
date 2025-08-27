<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Stablecoin\Services;

use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Stablecoin\Services\LiquidationService;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use stdClass;
use Tests\ServiceTestCase;

class LiquidationServiceTest extends ServiceTestCase
{
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected LiquidationService $service;

    protected $exchangeRateService;

    protected $collateralService;

    protected $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exchangeRateService = Mockery::mock(ExchangeRateService::class);
        $this->collateralService = Mockery::mock(CollateralService::class);
        $this->walletService = Mockery::mock(WalletService::class);

        // Mock DB facade
        DB::shouldReceive('transaction')->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->service = new LiquidationService(
            $this->exchangeRateService,
            $this->collateralService,
            $this->walletService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_calculate_liquidation_reward()
    {
        $stablecoin = new stdClass();
        $stablecoin->liquidation_penalty = 0.1;
        $stablecoin->min_collateral_ratio = 1.2;

        $position = Mockery::mock(StablecoinCollateralPosition::class);
        $position->shouldReceive('getAttribute')->with('stablecoin')->andReturn($stablecoin);
        $position->shouldReceive('getAttribute')->with('collateral_amount')->andReturn(110000);
        $position->shouldReceive('getAttribute')->with('debt_amount')->andReturn(100000);
        $position->shouldReceive('getAttribute')->with('collateral_ratio')->andReturn(1.1);
        $position->shouldReceive('getAttribute')->with('collateral_asset_code')->andReturn('USD');
        $position->shouldReceive('shouldAutoLiquidate')->andReturn(true);

        $reward = $this->service->calculateLiquidationReward($position);

        $this->assertArrayHasKey('penalty', $reward);
        $this->assertArrayHasKey('reward', $reward);
        $this->assertArrayHasKey('collateral_seized', $reward);
        $this->assertArrayHasKey('eligible', $reward);
        $this->assertTrue($reward['eligible']);
        $this->assertEquals(11000, $reward['penalty']); // 10% penalty on 110,000 collateral
    }

    #[Test]
    public function it_prevents_liquidation_of_healthy_positions()
    {
        $position = Mockery::mock(StablecoinCollateralPosition::class);
        $position->shouldReceive('shouldAutoLiquidate')->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Position is not eligible for liquidation');

        $this->service->liquidatePosition($position);
    }

    #[Test]
    public function it_validates_liquidation_eligibility()
    {
        $position = Mockery::mock(StablecoinCollateralPosition::class);
        $position->shouldReceive('shouldAutoLiquidate')->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Position is not eligible for liquidation');

        $this->service->liquidatePosition($position);
    }

    #[Test]
    public function it_calculates_liquidation_rewards_correctly()
    {
        $stablecoin = new stdClass();
        $stablecoin->liquidation_penalty = 0.1; // 10% penalty
        $stablecoin->min_collateral_ratio = 1.2;

        $position = Mockery::mock(StablecoinCollateralPosition::class);
        $position->shouldReceive('getAttribute')->with('stablecoin')->andReturn($stablecoin);
        $position->shouldReceive('getAttribute')->with('collateral_amount')->andReturn(120000);
        $position->shouldReceive('getAttribute')->with('debt_amount')->andReturn(100000);
        $position->shouldReceive('getAttribute')->with('collateral_ratio')->andReturn(1.2);
        $position->shouldReceive('getAttribute')->with('collateral_asset_code')->andReturn('USD');
        $position->shouldReceive('shouldAutoLiquidate')->andReturn(true);

        $reward = $this->service->calculateLiquidationReward($position);

        $expectedPenalty = 12000; // 10% of 120,000
        $expectedReward = 6000; // 50% of penalty goes to liquidator

        $this->assertEquals($expectedPenalty, $reward['penalty']);
        $this->assertEquals($expectedReward, $reward['reward']);
        $this->assertEquals(120000, $reward['collateral_seized']);
        $this->assertTrue($reward['eligible']);
    }
}
