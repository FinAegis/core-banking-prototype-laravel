<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exchange\Sagas;

use App\Domain\Exchange\Events\InventoryImbalanceDetected;
use App\Domain\Exchange\Events\LiquidityAdded;
use App\Domain\Exchange\Events\LiquidityRemoved;
use App\Domain\Exchange\Events\MarketVolatilityChanged;
use App\Domain\Exchange\Events\OrderExecuted;
use App\Domain\Exchange\Events\SpreadAdjusted;
use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Sagas\SpreadManagementSaga;
use App\Domain\Exchange\Services\LiquidityPoolService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SpreadManagementSagaTest extends TestCase
{
    private SpreadManagementSaga $saga;

    /** @var LiquidityPoolService&MockInterface */
    private MockInterface $poolService;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var LiquidityPoolService&MockInterface $poolService */
        $poolService = Mockery::mock(LiquidityPoolService::class);
        $this->poolService = $poolService;

        $this->saga = new SpreadManagementSaga(
            $poolService
        );

        Event::fake();
        Cache::flush();
    }

    public function test_recalculates_spread_on_liquidity_added(): void
    {
        // Arrange
        $poolId = 'pool-123';
        $event = new LiquidityAdded(
            poolId: $poolId,
            providerId: 'provider-456',
            baseAmount: '100',
            quoteAmount: '50000',
            sharesMinted: '100',
            newBaseReserve: '1100',
            newQuoteReserve: '50050000',
            newTotalShares: '1100'
        );

        $pool = new LiquidityPool();
        $pool->pool_id = $poolId;
        $pool->base_currency = 'BTC';
        $pool->quote_currency = 'USDT';
        $pool->base_reserve = '1000';
        $pool->quote_reserve = '50000000';

        $metrics = [
            'tvl'    => 100000000,
            'apy_7d' => 15.5,
        ];

        $this->poolService->shouldReceive('getPool')
            ->with($poolId)
            ->andReturn($pool);

        $this->poolService->shouldReceive('getPoolMetrics')
            ->with($poolId)
            ->andReturn($metrics);

        $this->poolService->shouldReceive('updatePoolParameters')
            ->once();

        // Act
        $this->saga->onLiquidityAdded($event);

        // Assert
        Event::assertDispatched(SpreadAdjusted::class, function ($event) use ($poolId) {
            return $event->poolId === $poolId
                && $event->reason === 'liquidity_added';
        });
    }

    public function test_widens_spread_on_liquidity_removed(): void
    {
        // Arrange
        $poolId = 'pool-123';
        $event = new LiquidityRemoved(
            poolId: $poolId,
            providerId: 'provider-456',
            sharesBurned: '100',
            baseAmount: '100',
            quoteAmount: '50000',
            newBaseReserve: '900',
            newQuoteReserve: '44950000',
            newTotalShares: '900'
        );

        $pool = new LiquidityPool();
        $pool->pool_id = $poolId;
        $pool->base_currency = 'BTC';
        $pool->quote_currency = 'USDT';
        $pool->base_reserve = '900'; // Lower reserves after removal
        $pool->quote_reserve = '45000000';

        $metrics = [
            'tvl'    => 90000000,
            'apy_7d' => 12.5,
        ];

        $this->poolService->shouldReceive('getPool')
            ->with($poolId)
            ->andReturn($pool);

        $this->poolService->shouldReceive('getPoolMetrics')
            ->with($poolId)
            ->andReturn($metrics);

        $this->poolService->shouldReceive('updatePoolParameters')
            ->once();

        // Act
        $this->saga->onLiquidityRemoved($event);

        // Assert
        Event::assertDispatched(SpreadAdjusted::class, function ($event) use ($poolId) {
            return $event->poolId === $poolId
                && $event->reason === 'liquidity_removed';
        });
    }

    public function test_detects_inventory_imbalance_on_order_executed(): void
    {
        // Arrange
        $poolId = 'pool-123';
        $event = new OrderExecuted(
            orderId: 'order-789',
            baseCurrency: 'BTC',
            quoteCurrency: 'USDT',
            side: 'buy',
            amount: 10,
            price: 50000,
            executedAt: now()
        );

        $pool = new LiquidityPool();
        $pool->pool_id = $poolId;
        $pool->base_currency = 'BTC';
        $pool->quote_currency = 'USDT';
        $pool->base_reserve = '800'; // Imbalanced after large buy
        $pool->quote_reserve = '45000000';

        $this->poolService->shouldReceive('getPoolByPair')
            ->with('BTC', 'USDT')
            ->andReturn($pool);

        $this->poolService->shouldReceive('getPool')
            ->with($poolId)
            ->andReturn($pool);

        // Since this pool is critically imbalanced, rebalancePool will be called
        $this->poolService->shouldReceive('rebalancePool')
            ->with($poolId, '0.5')
            ->once();

        // Act
        $this->saga->onOrderExecuted($event);

        // Assert - No immediate assertion as this updates cache
        $this->addToAssertionCount(1);
    }

    public function test_triggers_rebalancing_on_critical_imbalance(): void
    {
        // Arrange
        $poolId = 'pool-123';

        // Create severely imbalanced pool
        $pool = new LiquidityPool();
        $pool->pool_id = $poolId;
        $pool->base_currency = 'BTC';
        $pool->quote_currency = 'USDT';
        $pool->base_reserve = '200'; // Very low base reserve
        $pool->quote_reserve = '50000000'; // High quote reserve

        $event = new OrderExecuted(
            orderId: 'order-789',
            baseCurrency: 'BTC',
            quoteCurrency: 'USDT',
            side: 'buy',
            amount: 50,
            price: 50000,
            executedAt: now()
        );

        $this->poolService->shouldReceive('getPoolByPair')
            ->with('BTC', 'USDT')
            ->andReturn($pool);

        $this->poolService->shouldReceive('getPool')
            ->with($poolId)
            ->andReturn($pool);

        $this->poolService->shouldReceive('rebalancePool')
            ->with($poolId, '0.5')
            ->once();

        // Act
        $this->saga->onOrderExecuted($event);

        // Assert - rebalancePool should be called due to critical imbalance
        $this->addToAssertionCount(1); // Assertion is in the mock expectation
    }

    public function test_adjusts_spread_for_market_volatility(): void
    {
        // Arrange
        $event = new MarketVolatilityChanged(
            assetCode: 'BTC',
            oldVolatility: 0.02,
            newVolatility: 0.08, // High volatility
            level: 'high',
            timestamp: now()
        );

        $pool1 = new LiquidityPool();
        $pool1->pool_id = 'pool-1';
        $pool1->base_currency = 'BTC';
        $pool1->quote_currency = 'USDT';
        $pool1->base_reserve = '1000';
        $pool1->quote_reserve = '50000000';

        $pool2 = new LiquidityPool();
        $pool2->pool_id = 'pool-2';
        $pool2->base_currency = 'ETH';
        $pool2->quote_currency = 'BTC';
        $pool2->base_reserve = '10000';
        $pool2->quote_reserve = '100';

        $pools = collect([$pool1, $pool2]);

        $this->poolService->shouldReceive('getActivePools')
            ->andReturn($pools);

        $this->poolService->shouldReceive('getPool')
            ->andReturn($pool1, $pool2);

        $this->poolService->shouldReceive('getPoolMetrics')
            ->andReturn(['tvl' => 100000000, 'apy_7d' => 15]);

        $this->poolService->shouldReceive('updatePoolParameters')
            ->twice(); // Should update both pools with BTC

        // Act
        $this->saga->onMarketVolatilityChanged($event);

        // Assert
        Event::assertDispatched(SpreadAdjusted::class, function ($event) {
            return $event->reason === 'volatility_change';
        });
    }

    public function test_detects_moderate_inventory_imbalance(): void
    {
        // Arrange
        $poolId = 'pool-123';
        $event = new LiquidityAdded(
            poolId: $poolId,
            providerId: 'provider-456',
            baseAmount: '100',
            quoteAmount: '50000',
            sharesMinted: '100',
            newBaseReserve: '1400',
            newQuoteReserve: '30000000',
            newTotalShares: '1100'
        );

        // Create moderately imbalanced pool (25% base, 75% quote by value for 0.25 imbalance)
        $pool = new LiquidityPool();
        $pool->pool_id = $poolId;
        $pool->base_currency = 'BTC';
        $pool->quote_currency = 'USDT';
        $pool->base_reserve = '10000'; // 25% of total value
        $pool->quote_reserve = '30000'; // 75% of total value

        $metrics = [
            'tvl'    => 100000000,
            'apy_7d' => 15.5,
        ];

        $this->poolService->shouldReceive('getPool')
            ->with($poolId)
            ->andReturn($pool);

        $this->poolService->shouldReceive('getPoolMetrics')
            ->with($poolId)
            ->andReturn($metrics);

        $this->poolService->shouldReceive('updatePoolParameters')
            ->once();

        // Act
        $this->saga->onLiquidityAdded($event);

        // Assert
        Event::assertDispatched(InventoryImbalanceDetected::class, function ($event) use ($poolId) {
            return $event->poolId === $poolId
                && $event->severity === 'moderate'
                && $event->recommendedAction === 'monitor';
        });
    }

    public function test_detects_critical_inventory_imbalance(): void
    {
        // Arrange
        $poolId = 'pool-123';
        $event = new LiquidityAdded(
            poolId: $poolId,
            providerId: 'provider-456',
            baseAmount: '100',
            quoteAmount: '50000',
            sharesMinted: '100',
            newBaseReserve: '1800',
            newQuoteReserve: '10000000',
            newTotalShares: '1100'
        );

        // Create critically imbalanced pool (5% base, 95% quote by value for 0.45 imbalance)
        $pool = new LiquidityPool();
        $pool->pool_id = $poolId;
        $pool->base_currency = 'BTC';
        $pool->quote_currency = 'USDT';
        $pool->base_reserve = '5000'; // 5% of total value
        $pool->quote_reserve = '95000'; // 95% of total value

        $metrics = [
            'tvl'    => 100000000,
            'apy_7d' => 15.5,
        ];

        $this->poolService->shouldReceive('getPool')
            ->with($poolId)
            ->andReturn($pool);

        $this->poolService->shouldReceive('getPoolMetrics')
            ->with($poolId)
            ->andReturn($metrics);

        $this->poolService->shouldReceive('updatePoolParameters')
            ->once();

        // Act
        $this->saga->onLiquidityAdded($event);

        // Assert
        Event::assertDispatched(InventoryImbalanceDetected::class, function ($event) use ($poolId) {
            return $event->poolId === $poolId
                && $event->severity === 'critical'
                && $event->recommendedAction === 'rebalance_urgent';
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Cache::flush(); // Clear cache to prevent memory leaks
        parent::tearDown();
    }
}
