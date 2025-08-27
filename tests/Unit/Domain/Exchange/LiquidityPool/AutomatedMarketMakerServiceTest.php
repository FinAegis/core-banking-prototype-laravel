<?php

namespace Tests\Unit\Domain\Exchange\LiquidityPool;

use App\Domain\Exchange\Contracts\PriceAggregatorInterface;
use App\Domain\Exchange\LiquidityPool\Services\AutomatedMarketMakerService;
use App\Domain\Exchange\Projections\LiquidityPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\ServiceTestCase;

class AutomatedMarketMakerServiceTest extends ServiceTestCase
{
    use RefreshDatabase;

    private AutomatedMarketMakerService $service;

    private $priceAggregator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->priceAggregator = Mockery::mock(PriceAggregatorInterface::class);
        $this->service = new AutomatedMarketMakerService($this->priceAggregator);
    }

    #[Test]
    public function test_generates_market_making_orders_for_pool()
    {
        // Create a test pool
        $pool = LiquidityPool::create([
            'pool_id'            => 'test-pool-1',
            'base_currency'      => 'BTC',
            'quote_currency'     => 'USD',
            'base_reserve'       => '10',
            'quote_reserve'      => '400000',
            'total_shares'       => '1000',
            'fee_rate'           => '0.003',
            'is_active'          => true,
            'volume_24h'         => '50000',
            'fees_collected_24h' => '150',
        ]);

        // Mock external price
        $this->priceAggregator->shouldReceive('getAggregatedPrice')
            ->with('BTC/USD')
            ->andReturn(['price' => '40000', 'source' => 'aggregated']);

        // Generate orders
        $orders = $this->service->generateMarketMakingOrders($pool->pool_id);

        // Assertions
        $this->assertNotEmpty($orders);
        $this->assertCount(10, $orders); // 5 buy + 5 sell orders

        // Check buy orders
        $buyOrders = array_filter($orders, fn ($order) => $order['type'] === 'buy');
        $this->assertCount(5, $buyOrders);

        foreach ($buyOrders as $order) {
            $this->assertArrayHasKey('price', $order);
            $this->assertArrayHasKey('quantity', $order);
            $this->assertArrayHasKey('level', $order);
            $this->assertEquals('amm', $order['source']);
            $this->assertEquals($pool->pool_id, $order['pool_id']);
        }

        // Check sell orders
        $sellOrders = array_filter($orders, fn ($order) => $order['type'] === 'sell');
        $this->assertCount(5, $sellOrders);
    }

    #[Test]
    public function test_adjusts_spread_based_on_market_conditions()
    {
        // Create a volatile pool
        $pool = LiquidityPool::create([
            'pool_id'            => 'volatile-pool',
            'base_currency'      => 'ETH',
            'quote_currency'     => 'USD',
            'base_reserve'       => '100',
            'quote_reserve'      => '200000',
            'total_shares'       => '1000',
            'fee_rate'           => '0.003',
            'is_active'          => true,
            'volume_24h'         => '1000000', // High volume
            'fees_collected_24h' => '3000',
        ]);

        // Mock high price deviation
        $this->priceAggregator->shouldReceive('getAggregatedPrice')
            ->with('ETH/USD')
            ->andReturn(['price' => '2500']); // External price differs from pool price (2000)

        $orders = $this->service->generateMarketMakingOrders($pool->pool_id);

        // In volatile conditions, spreads should be wider
        $buyOrders = array_filter($orders, fn ($order) => $order['type'] === 'buy');
        $firstBuyOrder = array_values($buyOrders)[0];
        $poolPrice = 2000; // 200000 / 100

        // Check that buy price is below pool price (at least 1% spread)
        $this->assertLessThanOrEqual($poolPrice * 0.99, (float) $firstBuyOrder['price']);
    }

    #[Test]
    public function test_calculates_order_sizes_based_on_reserves()
    {
        $pool = LiquidityPool::create([
            'pool_id'        => 'test-pool-2',
            'base_currency'  => 'ETH',
            'quote_currency' => 'USDC',
            'base_reserve'   => '1000', // Large reserves
            'quote_reserve'  => '2000000',
            'total_shares'   => '10000',
            'fee_rate'       => '0.003',
            'is_active'      => true,
        ]);

        $this->priceAggregator->shouldReceive('getAggregatedPrice')
            ->andReturn(['price' => '2000']);

        $orders = $this->service->generateMarketMakingOrders($pool->pool_id);

        // Check that order sizes are proportional to reserves (10% of reserves / 5 levels)
        $sellOrders = array_filter($orders, fn ($order) => $order['type'] === 'sell');
        $firstSellOrder = array_values($sellOrders)[0];

        // Each sell order should be ~2% of base reserves (10% / 5 levels)
        $expectedQuantity = 1000 * 0.1 / 5; // 20 ETH per order
        $this->assertEquals(20, (float) $firstSellOrder['quantity']);
    }

    #[Test]
    public function test_adjusts_market_making_parameters()
    {
        $pool = LiquidityPool::create([
            'pool_id'        => 'test-pool-3',
            'base_currency'  => 'BTC',
            'quote_currency' => 'USD',
            'base_reserve'   => '10',
            'quote_reserve'  => '400000',
            'total_shares'   => '1000',
            'fee_rate'       => '0.003',
            'is_active'      => true,
        ]);

        $adjustments = $this->service->adjustMarketMakingParameters($pool->pool_id);

        $this->assertIsArray($adjustments);
        // Adjustments depend on performance metrics
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
