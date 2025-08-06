<?php

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Stablecoin\Events\CollateralPositionLiquidated;
use App\Domain\Stablecoin\Events\StablecoinBurned;
use App\Domain\Stablecoin\Events\StablecoinMinted;
use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use App\Domain\Stablecoin\Services\DemoStablecoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DemoStablecoinServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DemoStablecoinService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure demo settings
        Config::set('demo.mode', true);
        Config::set('demo.features.auto_collateralize', true);
        Config::set('demo.demo_data.stablecoin.collateral_ratio', 1.5);
        Config::set('demo.demo_data.stablecoin.liquidation_threshold', 1.2);
        Config::set('demo.demo_data.stablecoin.stability_fee', 2.5);

        $this->service = new DemoStablecoinService();
    }

    /** @test */
    public function it_can_mint_stablecoins_with_sufficient_collateral()
    {
        Event::fake();

        $transaction = $this->service->mint(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1 // 1 ETH worth ~$2000
        );

        $this->assertIsArray($transaction);
        $this->assertStringStartsWith('demo_tx_', $transaction['id']);
        $this->assertEquals('mint', $transaction['type']);
        $this->assertEquals(1000, $transaction['amount']);
        $this->assertEquals(1, $transaction['collateral']);
        $this->assertEquals('completed', $transaction['status']);
        $this->assertTrue($transaction['metadata']['demo_mode']);

        Event::assertDispatched(StablecoinMinted::class, function ($event) {
            return $event->account_uuid === 'acc_123'
                && $event->stablecoin_code === 'GUSD'
                && $event->amount === 1000;
        });
    }

    /** @test */
    public function it_throws_exception_for_insufficient_collateral()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient collateral');

        $this->service->mint(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 0.1 // Only ~$200 worth, need $1500
        );
    }

    /** @test */
    public function it_can_burn_stablecoins_and_release_collateral()
    {
        Event::fake();

        // First mint some stablecoins
        $this->service->mint(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1
        );

        // Then burn some
        $transaction = $this->service->burn(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 500
        );

        $this->assertIsArray($transaction);
        $this->assertEquals('burn', $transaction['type']);
        $this->assertEquals(500, $transaction['amount']);

        Event::assertDispatched(StablecoinBurned::class, function ($event) {
            return $event->account_uuid === 'acc_123'
                && $event->amount === 500;
        });
    }

    /** @test */
    public function it_closes_position_when_fully_burned()
    {
        // First mint
        $this->service->mint(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1
        );

        // Burn all
        $this->service->burn(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000
        );

        $position = StablecoinCollateralPosition::where('account_uuid', 'acc_123')
            ->where('stablecoin_code', 'GUSD')
            ->first();

        $this->assertEquals('closed', $position->status);
        $this->assertEquals(0, $position->debt_amount);
    }

    /** @test */
    public function it_can_adjust_collateral_position()
    {
        Event::fake();

        // First create a position
        $this->service->mint(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1
        );

        $position = StablecoinCollateralPosition::where('account_uuid', 'acc_123')->first();

        // Add more collateral
        $result = $this->service->adjustPosition(
            positionId: $position->uuid,
            collateral: 0.5,
            debt: 0
        );

        $this->assertArrayHasKey('position_id', $result);
        $this->assertArrayHasKey('collateral', $result);
        $this->assertArrayHasKey('health', $result);
    }

    /** @test */
    public function it_can_get_position_details()
    {
        // Create a position
        $this->service->mint(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1
        );

        $position = StablecoinCollateralPosition::where('account_uuid', 'acc_123')->first();

        $details = $this->service->getPosition($position->uuid);

        $this->assertEquals($position->uuid, $details['position_id']);
        $this->assertEquals('acc_123', $details['account_id']);
        $this->assertEquals('GUSD', $details['stablecoin_id']);
        $this->assertEquals(1, $details['collateral']);
        $this->assertEquals(1000, $details['debt']);
        $this->assertEquals('healthy', $details['health']);
    }

    /** @test */
    public function it_can_check_and_liquidate_at_risk_positions()
    {
        Event::fake();

        // Create an at-risk position
        StablecoinCollateralPosition::create([
            'uuid'                  => 'pos_risk_1',
            'account_uuid'          => 'acc_456',
            'stablecoin_code'       => 'GUSD',
            'collateral_asset_code' => 'ETH',
            'collateral_amount'     => 0.5,
            'debt_amount'           => 1000,
            'collateral_ratio'      => 1.0, // Below threshold
            'status'                => 'active',
        ]);

        $result = $this->service->checkLiquidations();

        $this->assertEquals(1, $result['checked']);
        $this->assertEquals(1, $result['liquidated']);
        $this->assertCount(1, $result['liquidation_details']);

        Event::assertDispatched(CollateralPositionLiquidated::class);
    }

    /** @test */
    public function it_provides_system_statistics()
    {
        // Create some positions
        StablecoinCollateralPosition::create([
            'uuid'                  => 'pos_1',
            'account_uuid'          => 'acc_1',
            'stablecoin_code'       => 'GUSD',
            'collateral_asset_code' => 'ETH',
            'collateral_amount'     => 1,
            'debt_amount'           => 1000,
            'collateral_ratio'      => 2.0,
            'status'                => 'active',
        ]);

        StablecoinCollateralPosition::create([
            'uuid'                  => 'pos_2',
            'account_uuid'          => 'acc_2',
            'stablecoin_code'       => 'GUSD',
            'collateral_asset_code' => 'ETH',
            'collateral_amount'     => 0.5,
            'debt_amount'           => 500,
            'collateral_ratio'      => 2.0,
            'status'                => 'active',
        ]);

        $stats = $this->service->getSystemStats();

        $this->assertEquals(1500, $stats['total_minted']);
        $this->assertEquals(1.5, $stats['total_collateral']);
        $this->assertEquals(1500, $stats['total_debt']);
        $this->assertEquals(2, $stats['active_positions']);
        $this->assertTrue($stats['demo']);
    }

    /** @test */
    public function it_can_get_account_positions()
    {
        // Create positions for an account
        StablecoinCollateralPosition::create([
            'uuid'                  => 'pos_1',
            'account_uuid'          => 'acc_123',
            'stablecoin_code'       => 'GUSD',
            'collateral_asset_code' => 'ETH',
            'collateral_amount'     => 1,
            'debt_amount'           => 1000,
            'collateral_ratio'      => 2.0,
            'status'                => 'active',
        ]);

        StablecoinCollateralPosition::create([
            'uuid'                  => 'pos_2',
            'account_uuid'          => 'acc_123',
            'stablecoin_code'       => 'EUSD',
            'collateral_asset_code' => 'ETH',
            'collateral_amount'     => 0.5,
            'debt_amount'           => 500,
            'collateral_ratio'      => 2.0,
            'status'                => 'active',
        ]);

        $result = $this->service->getAccountPositions('acc_123');

        $this->assertEquals('acc_123', $result['account_id']);
        $this->assertCount(2, $result['positions']);
        $this->assertEquals(1.5, $result['total_collateral']);
        $this->assertEquals(1500, $result['total_debt']);
    }

    /** @test */
    public function it_prevents_burning_more_than_debt()
    {
        // Mint some stablecoins
        $this->service->mint(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot burn more than debt amount');

        // Try to burn more
        $this->service->burn(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 2000
        );
    }

    /** @test */
    public function it_throws_exception_when_no_position_exists_for_burn()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No active position found');

        $this->service->burn(
            accountId: 'acc_999',
            stablecoinId: 'GUSD',
            amount: 100
        );
    }
}