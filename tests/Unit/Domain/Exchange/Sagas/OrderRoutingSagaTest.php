<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exchange\Sagas;

use App\Domain\Exchange\Events\OrderPlaced;
use App\Domain\Exchange\Events\OrderRouted;
use App\Domain\Exchange\Events\OrderSplit;
use App\Domain\Exchange\Events\RoutingFailed;
use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Sagas\OrderRoutingSaga;
use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Domain\Exchange\Services\OrderService;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\UnitTestCase;

class OrderRoutingSagaTest extends UnitTestCase
{
    private OrderRoutingSaga $saga;

    /** @var LiquidityPoolService&MockInterface */
    private MockInterface $poolService;

    /** @var OrderService&MockInterface */
    private MockInterface $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake events first to capture all dispatched events
        Event::fake();

        /** @var LiquidityPoolService&MockInterface $poolService */
        $poolService = Mockery::mock(LiquidityPoolService::class);
        $this->poolService = $poolService;

        /** @var OrderService&MockInterface $orderService */
        $orderService = Mockery::mock(OrderService::class);
        $this->orderService = $orderService;

        $this->saga = new OrderRoutingSaga(
            $poolService,
            $orderService
        );
    }

    public function test_routes_order_to_single_pool_with_best_price(): void
    {
        // Arrange
        $event = new OrderPlaced(
            orderId: 'order-123',
            accountId: 'user-456',
            type: 'buy',
            orderType: 'market',
            baseCurrency: 'BTC',
            quoteCurrency: 'USDT',
            amount: '0.5',
            price: null
        );

        $pool1 = $this->createPool('pool-1', 'BTC', 'USDT', 10, 500000);
        $pool2 = $this->createPool('pool-2', 'BTC', 'USDT', 5, 250000);

        $this->poolService->shouldReceive('getPoolsForPair')
            ->with('BTC', 'USDT')
            ->andReturn([$pool1, $pool2]);

        $this->orderService->shouldReceive('updateOrderRouting')
            ->once();

        // Act
        $this->saga->onOrderPlaced($event);

        // Assert
        Event::assertDispatched(OrderRouted::class, function ($event) {
            return $event->orderId === 'order-123'
                && $event->poolId === 'pool-1'; // Pool 1 has better liquidity
        });

        Event::assertNotDispatched(OrderSplit::class);
    }

    public function test_splits_large_order_across_multiple_pools(): void
    {
        // Arrange
        $event = new OrderPlaced(
            orderId: 'order-789',
            accountId: 'user-456',
            type: 'buy',
            orderType: 'market',
            baseCurrency: 'BTC',
            quoteCurrency: 'USDT',
            amount: '5.0', // Large order
            price: null
        );

        $pool1 = $this->createPool('pool-1', 'BTC', 'USDT', 2, 100000);
        $pool2 = $this->createPool('pool-2', 'BTC', 'USDT', 3, 150000);
        $pool3 = $this->createPool('pool-3', 'BTC', 'USDT', 1, 50000);

        $this->poolService->shouldReceive('getPoolsForPair')
            ->with('BTC', 'USDT')
            ->andReturn([$pool1, $pool2, $pool3]);

        $this->orderService->shouldReceive('createChildOrder')
            ->times(3);

        // Act
        $this->saga->onOrderPlaced($event);

        // Assert
        Event::assertDispatched(OrderSplit::class, function ($event) {
            return $event->orderId === 'order-789'
                && count($event->splits) > 1;
        });

        Event::assertDispatched(OrderRouted::class);
    }

    public function test_handles_no_liquidity_available(): void
    {
        // Arrange
        $event = new OrderPlaced(
            orderId: 'order-404',
            accountId: 'user-456',
            type: 'buy',
            orderType: 'market',
            baseCurrency: 'XYZ',
            quoteCurrency: 'ABC',
            amount: '1.0',
            price: null
        );

        $this->poolService->shouldReceive('getPoolsForPair')
            ->with('XYZ', 'ABC')
            ->andReturn([]);

        $this->orderService->shouldReceive('rejectOrder')
            ->with('order-404', 'No liquidity available')
            ->once();

        // Act
        $this->saga->onOrderPlaced($event);

        // Assert
        Event::assertDispatched(RoutingFailed::class, function ($event) {
            return $event->orderId === 'order-404'
                && $event->reason === 'No liquidity available for trading pair';
        });
    }

    public function test_considers_price_impact_when_routing(): void
    {
        // Arrange
        $event = new OrderPlaced(
            orderId: 'order-impact',
            accountId: 'user-456',
            type: 'sell',
            orderType: 'market',
            baseCurrency: 'ETH',
            quoteCurrency: 'USDT',
            amount: '10.0',
            price: null
        );

        // Small pool with high impact
        $smallPool = $this->createPool('small-pool', 'ETH', 'USDT', 5, 15000);

        // Large pool with low impact
        $largePool = $this->createPool('large-pool', 'ETH', 'USDT', 100, 300000);

        $this->poolService->shouldReceive('getPoolsForPair')
            ->with('ETH', 'USDT')
            ->andReturn([$smallPool, $largePool]);

        $this->orderService->shouldReceive('updateOrderRouting')
            ->once();

        // Act
        $this->saga->onOrderPlaced($event);

        // Assert - Should route to large pool despite potentially slightly worse price
        Event::assertDispatched(OrderRouted::class, function ($event) {
            return $event->poolId === 'large-pool';
        });
    }

    public function test_filters_inactive_pools(): void
    {
        // Arrange
        $event = new OrderPlaced(
            orderId: 'order-filter',
            accountId: 'user-456',
            type: 'buy',
            orderType: 'market',
            baseCurrency: 'BTC',
            quoteCurrency: 'USDT',
            amount: '1.0',
            price: null
        );

        $activePool = $this->createPool('active', 'BTC', 'USDT', 10, 500000, 'active');
        $inactivePool = $this->createPool('inactive', 'BTC', 'USDT', 20, 1000000, 'inactive');

        $this->poolService->shouldReceive('getPoolsForPair')
            ->with('BTC', 'USDT')
            ->andReturn([$activePool, $inactivePool]);

        $this->orderService->shouldReceive('updateOrderRouting')
            ->once();

        // Act
        $this->saga->onOrderPlaced($event);

        // Assert - Should only route to active pool
        Event::assertDispatched(OrderRouted::class, function ($event) {
            return $event->poolId === 'active';
        });
    }

    public function test_applies_fee_tiers_in_routing_decision(): void
    {
        // Arrange
        $event = new OrderPlaced(
            orderId: 'order-fees',
            accountId: 'user-456',
            type: 'buy',
            orderType: 'market',
            baseCurrency: 'BTC',
            quoteCurrency: 'USDT',
            amount: '1.0',
            price: null
        );

        $lowFeePool = $this->createPool('low-fee', 'BTC', 'USDT', 10, 500000, 'active', 0.001);
        $highFeePool = $this->createPool('high-fee', 'BTC', 'USDT', 10, 500000, 'active', 0.005);

        $this->poolService->shouldReceive('getPoolsForPair')
            ->with('BTC', 'USDT')
            ->andReturn([$lowFeePool, $highFeePool]);

        $this->orderService->shouldReceive('updateOrderRouting')
            ->once();

        // Act
        $this->saga->onOrderPlaced($event);

        // Assert - Should route to low fee pool with similar liquidity
        Event::assertDispatched(OrderRouted::class, function ($event) {
            return $event->poolId === 'low-fee'
                && $event->feeTier === 0.001;
        });
    }

    private function createPool(
        string $poolId,
        string $baseCurrency,
        string $quoteCurrency,
        float $baseReserve,
        float $quoteReserve,
        string $status = 'active',
        float $feeTier = 0.003
    ): LiquidityPool {
        $pool = new LiquidityPool();
        $pool->pool_id = $poolId;
        $pool->base_currency = $baseCurrency;
        $pool->quote_currency = $quoteCurrency;
        $pool->base_reserve = (string) $baseReserve;
        $pool->quote_reserve = (string) $quoteReserve;
        $pool->is_active = ($status === 'active');
        $pool->metadata = ['fee_tier' => $feeTier];

        return $pool;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
