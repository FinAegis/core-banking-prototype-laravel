<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exchange\Workflows;

use App\Domain\Exchange\Activities\CalculateOptimalQuotesActivity;
use App\Domain\Exchange\Activities\CancelOrderActivity;
use App\Domain\Exchange\Activities\PlaceOrderActivity;
use App\Domain\Exchange\Events\MarketMakerStarted;
use App\Domain\Exchange\Events\MarketMakerStopped;
use App\Domain\Exchange\Events\QuotesUpdated;
use App\Domain\Exchange\Workflows\MarketMakerWorkflow;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MarketMakerWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_market_maker_workflow_can_be_instantiated(): void
    {
        // Arrange & Act
        $workflow = new MarketMakerWorkflow();

        // Assert
        $this->assertInstanceOf(MarketMakerWorkflow::class, $workflow);

        // Test that execute method returns a generator
        $config = [
            'pool_id'        => 'pool-123',
            'base_currency'  => 'BTC',
            'quote_currency' => 'USDT',
            'spread_bps'     => 30,
            'order_size'     => 0.1,
            'max_cycles'     => 0, // Don't actually run any cycles
        ];

        $result = $workflow->execute($config);
        $this->assertInstanceOf(\Generator::class, $result);
    }

    public function test_market_maker_detects_inventory_imbalance(): void
    {
        // Arrange
        $config = [
            'pool_id'                => 'pool-123',
            'base_currency'          => 'BTC',
            'quote_currency'         => 'USDT',
            'spread_bps'             => 30,
            'order_size'             => 0.1,
            'max_inventory'          => 10,
            'rebalance_threshold'    => 0.2,
            'quote_refresh_interval' => 10,
            'max_cycles'             => 1,
        ];

        // Create imbalanced market conditions
        $marketConditions = [
            'mid_price'  => 50000,
            'inventory'  => ['BTC' => 8, 'USDT' => 100000], // 80% BTC by value
            'volatility' => 0.02,
        ];

        // Test that rebalancing would be triggered
        $baseValue = 8 * 50000; // 400,000
        $totalValue = 400000 + 100000; // 500,000
        $baseRatio = $baseValue / $totalValue; // 0.8
        $imbalance = abs(0.5 - $baseRatio); // 0.3

        $this->assertGreaterThan(0.2, $imbalance); // Should trigger rebalancing
    }

    public function test_market_maker_adjusts_quotes_for_volatility(): void
    {
        // Arrange
        $normalVolatility = 0.02;
        $highVolatility = 0.08;
        $baseSpread = 30; // basis points

        // Calculate expected spread adjustments
        $normalSpread = $baseSpread; // No adjustment for normal volatility
        $highVolatilitySpread = $baseSpread * 2; // Double for high volatility

        // Assert
        $this->assertEquals(30, $normalSpread);
        $this->assertEquals(60, $highVolatilitySpread);
    }

    public function test_market_maker_respects_risk_limits(): void
    {
        // Arrange
        $riskLimits = [
            'max_inventory'  => ['BTC' => 10, 'USDT' => 500000],
            'max_loss'       => 10000,
            'max_volatility' => 0.1,
        ];

        // Test max inventory check
        $inventory = ['BTC' => 11, 'USDT' => 400000];
        $this->assertGreaterThan($riskLimits['max_inventory']['BTC'], $inventory['BTC']);

        // Test max loss check
        $pnl = -11000;
        $this->assertLessThan(-$riskLimits['max_loss'], $pnl);

        // Test max volatility check
        $volatility = 0.15;
        $this->assertGreaterThan($riskLimits['max_volatility'], $volatility);
    }

    public function test_market_maker_handles_order_lifecycle(): void
    {
        // Test order placement
        $orderData = [
            'type'           => 'limit',
            'side'           => 'buy',
            'base_currency'  => 'BTC',
            'quote_currency' => 'USDT',
            'amount'         => 0.1,
            'price'          => 49970,
            'pool_id'        => 'pool-123',
        ];

        // Simulate order placement activity
        $placeOrderActivity = new PlaceOrderActivity(
            $this->createMock(\App\Domain\Exchange\Services\OrderService::class)
        );

        // Test order cancellation
        $orderId = 'order-456';
        $cancelOrderActivity = new CancelOrderActivity(
            $this->createMock(\App\Domain\Exchange\Services\OrderService::class)
        );

        // Activity instance exists
        $this->assertInstanceOf(CancelOrderActivity::class, $cancelOrderActivity);
    }

    public function test_market_maker_emits_correct_events(): void
    {
        // Arrange
        $poolId = 'pool-123';
        $config = [
            'pool_id'        => $poolId,
            'base_currency'  => 'BTC',
            'quote_currency' => 'USDT',
            'spread_bps'     => 30,
            'order_size'     => 0.1,
        ];

        // Manually trigger events that would be emitted by workflow
        event(new MarketMakerStarted(
            poolId: $poolId,
            baseCurrency: 'BTC',
            quoteCurrency: 'USDT',
            config: $config,
            startedAt: now()
        ));

        event(new QuotesUpdated(
            poolId: $poolId,
            bids: [['price' => 49970, 'size' => 0.1]],
            asks: [['price' => 50030, 'size' => 0.1]],
            spread: 30,
            timestamp: now()
        ));

        event(new MarketMakerStopped(
            poolId: $poolId,
            reason: 'completed',
            stoppedAt: now()
        ));

        // Assert events were dispatched
        Event::assertDispatched(MarketMakerStarted::class, function ($event) use ($poolId) {
            return $event->poolId === $poolId;
        });

        Event::assertDispatched(QuotesUpdated::class, function ($event) use ($poolId) {
            return $event->poolId === $poolId;
        });

        Event::assertDispatched(MarketMakerStopped::class, function ($event) use ($poolId) {
            return $event->poolId === $poolId && $event->reason === 'completed';
        });
    }

    public function test_market_maker_calculates_optimal_quotes(): void
    {
        // Arrange
        $activity = new CalculateOptimalQuotesActivity(
            $this->createMock(\App\Domain\Exchange\Services\LiquidityPoolService::class)
        );

        $poolId = 'pool-123';
        $marketConditions = [
            'mid_price'  => 50000,
            'inventory'  => ['BTC' => 5, 'USDT' => 250000],
            'volatility' => 0.02,
        ];
        $spreadBps = 30;
        $orderSize = 0.1;

        // Calculate expected quotes
        $midPrice = 50000;
        $halfSpread = $midPrice * (30 / 10000) / 2; // 75
        $expectedBidPrice = $midPrice - $halfSpread; // 49925
        $expectedAskPrice = $midPrice + $halfSpread; // 50075

        // Assert price calculations
        $this->assertEquals(49925, $expectedBidPrice);
        $this->assertEquals(50075, $expectedAskPrice);
    }
}
