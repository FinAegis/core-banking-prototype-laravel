<?php

namespace Tests\Unit\Domain\Exchange\LiquidityPool\Reactors;

use App\Domain\Exchange\Events\LiquidityAdded;
use App\Domain\Exchange\Events\LiquidityPoolRebalanced;
use App\Domain\Exchange\Events\LiquidityRemoved;
use App\Domain\Exchange\Events\LiquidityRewardsDistributed;
use App\Domain\Exchange\LiquidityPool\Reactors\SnapshotLiquidityPoolReactor;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SnapshotLiquidityPoolReactorTest extends TestCase
{
    private string $poolId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poolId = Str::uuid()->toString();
    }

    #[Test]
    public function test_takes_snapshot_on_large_liquidity_addition(): void
    {
        $event = new LiquidityAdded(
            poolId: $this->poolId,
            providerId: Str::uuid()->toString(),
            baseAmount: '50',
            quoteAmount: '1500000', // $1.5M - exceeds threshold
            sharesMinted: '1000',
            newBaseReserve: '150',
            newQuoteReserve: '3500000',
            newTotalShares: '2000'
        );

        // Create a partial mock of the reactor to spy on takeSnapshot
        $reactor = Mockery::mock(SnapshotLiquidityPoolReactor::class . '[takeSnapshot]')
            ->shouldAllowMockingProtectedMethods();
        $reactor->shouldReceive('takeSnapshot')
            ->once()
            ->with($this->poolId);

        $reactor->onLiquidityAdded($event);
    }

    #[Test]
    public function test_takes_snapshot_on_large_liquidity_removal(): void
    {
        $event = new LiquidityRemoved(
            poolId: $this->poolId,
            providerId: Str::uuid()->toString(),
            sharesBurned: '500',
            baseAmount: '25',
            quoteAmount: '1200000', // $1.2M - exceeds threshold
            newBaseReserve: '125',
            newQuoteReserve: '2300000',
            newTotalShares: '1500'
        );

        // Create a partial mock of the reactor to spy on takeSnapshot
        $reactor = Mockery::mock(SnapshotLiquidityPoolReactor::class . '[takeSnapshot]')
            ->shouldAllowMockingProtectedMethods();
        $reactor->shouldReceive('takeSnapshot')
            ->once()
            ->with($this->poolId);

        $reactor->onLiquidityRemoved($event);
    }

    #[Test]
    public function test_takes_snapshot_after_multiple_rebalances(): void
    {
        // Create a partial mock of the reactor to spy on takeSnapshot
        $reactor = Mockery::mock(SnapshotLiquidityPoolReactor::class . '[takeSnapshot]')
            ->shouldAllowMockingProtectedMethods();
        $reactor->shouldReceive('takeSnapshot')
            ->once()
            ->with($this->poolId);

        // Trigger 5 rebalances (threshold is 5)
        for ($i = 0; $i < 5; $i++) {
            $event = new LiquidityPoolRebalanced(
                poolId: $this->poolId,
                oldRatio: '0.05',
                newRatio: '0.048',
                rebalanceAmount: '1000',
                rebalanceCurrency: 'USD'
            );

            $reactor->onLiquidityPoolRebalanced($event);
        }
    }

    #[Test]
    public function test_takes_snapshot_on_large_reward_distribution(): void
    {
        $event = new LiquidityRewardsDistributed(
            poolId: $this->poolId,
            rewardAmount: '150000', // $150k - exceeds $100k threshold
            rewardCurrency: 'USD',
            totalShares: '10000'
        );

        // Create a partial mock of the reactor to spy on takeSnapshot
        $reactor = Mockery::mock(SnapshotLiquidityPoolReactor::class . '[takeSnapshot]')
            ->shouldAllowMockingProtectedMethods();
        $reactor->shouldReceive('takeSnapshot')
            ->once()
            ->with($this->poolId);

        $reactor->onLiquidityRewardsDistributed($event);
    }

    #[Test]
    public function test_does_not_snapshot_on_small_liquidity_addition(): void
    {
        $event = new LiquidityAdded(
            poolId: $this->poolId,
            providerId: Str::uuid()->toString(),
            baseAmount: '0.1',
            quoteAmount: '200', // $200 - below threshold
            sharesMinted: '10',
            newBaseReserve: '100.1',
            newQuoteReserve: '200200',
            newTotalShares: '1010'
        );

        // Create a partial mock of the reactor to spy on takeSnapshot
        $reactor = Mockery::mock(SnapshotLiquidityPoolReactor::class . '[takeSnapshot]')
            ->shouldAllowMockingProtectedMethods();
        $reactor->shouldNotReceive('takeSnapshot');

        $reactor->onLiquidityAdded($event);
    }
}
