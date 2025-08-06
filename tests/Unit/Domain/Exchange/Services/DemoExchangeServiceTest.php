<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exchange\Services;

use App\Domain\Exchange\Events\OrderMatched;
use App\Domain\Exchange\Events\OrderPlaced;
use App\Domain\Exchange\Projections\Order;
use App\Domain\Exchange\Projections\Trade;
use App\Domain\Exchange\Services\DemoExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DemoExchangeServiceTest extends TestCase
{
    use RefreshDatabase;

    private DemoExchangeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('demo.mode', true);
        Config::set('demo.features.auto_fill_orders', true);
        Config::set('demo.demo_data.exchange.spread_percentage', 0.1);
        Config::set('demo.demo_data.exchange.liquidity_multiplier', 10);
        Config::set('demo.demo_data.exchange.prices', [
            'EUR/USD' => 1.10,
            'GBP/USD' => 1.27,
            'GCU/USD' => 1.00,
            'BTC/USD' => 45000,
            'ETH/USD' => 2500,
        ]);

        // Create FeeCalculator mock using app container
        $feeCalculator = $this->createMock(\App\Domain\Exchange\Services\FeeCalculator::class);
        $this->app->instance(\App\Domain\Exchange\Services\FeeCalculator::class, $feeCalculator);

        $this->service = app(DemoExchangeService::class);
    }

    /** @test */
    public function it_can_place_demo_order_with_instant_matching()
    {
        Event::fake();

        $orderData = [
            'user_id'        => 1,
            'account_id'     => 1,
            'type'           => 'market',
            'side'           => 'buy',
            'base_currency'  => 'EUR',
            'quote_currency' => 'USD',
            'amount'         => 100,
            'price'          => null, // Market order
        ];

        $order = $this->service->placeOrder($orderData);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertStringStartsWith('demo_ord_', $order->order_id);
        $this->assertEquals('filled', $order->status);
        $this->assertEquals(100, $order->filled_amount);
        $this->assertEquals(0, $order->remaining_amount);
        $this->assertNotNull($order->filled_at);
        $this->assertTrue($order->metadata['demo_mode']);

        Event::assertDispatched(OrderPlaced::class);
        Event::assertDispatched(OrderMatched::class);
    }

    /** @test */
    public function it_can_place_limit_order_with_specified_price()
    {
        Event::fake();

        $orderData = [
            'user_id'        => 1,
            'account_id'     => 1,
            'type'           => 'limit',
            'side'           => 'sell',
            'base_currency'  => 'GBP',
            'quote_currency' => 'USD',
            'amount'         => 50,
            'price'          => 1.28,
        ];

        $order = $this->service->placeOrder($orderData);

        $this->assertEquals(1.28, $order->price);
        $this->assertEquals('filled', $order->status);
        $this->assertEquals(1.28, $order->average_price);
    }

    /** @test */
    public function it_can_cancel_pending_order()
    {
        Event::fake();

        // First create an order
        $order = Order::create([
            'order_id'       => 'demo_ord_test123',
            'account_id'     => 1,
            'type'           => 'buy',
            'order_type'     => 'limit',
            'base_currency'  => 'EUR',
            'quote_currency' => 'USD',
            'amount'         => 100,
            'filled_amount'  => 0,
            'price'          => 1.09,
            'status'         => 'pending',
        ]);

        $result = $this->service->cancelOrder($order->order_id, 1);

        $this->assertTrue($result);

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
    }

    /** @test */
    public function it_cannot_cancel_order_of_different_user()
    {
        $order = Order::create([
            'order_id'       => 'demo_ord_test456',
            'account_id'     => 2, // Different user
            'type'           => 'buy',
            'order_type'     => 'limit',
            'base_currency'  => 'EUR',
            'quote_currency' => 'USD',
            'amount'         => 100,
            'filled_amount'  => 0,
            'price'          => 1.09,
            'status'         => 'pending',
        ]);

        $result = $this->service->cancelOrder($order->order_id, 1); // Try with user 1

        $this->assertFalse($result);

        $order->refresh();
        $this->assertEquals('pending', $order->status);
    }

    /** @test */
    public function it_generates_demo_order_book_with_liquidity()
    {
        $orderBook = $this->service->getOrderBook('EUR', 'USD', 5);

        $this->assertArrayHasKey('pair', $orderBook);
        $this->assertArrayHasKey('bids', $orderBook);
        $this->assertArrayHasKey('asks', $orderBook);
        $this->assertArrayHasKey('timestamp', $orderBook);
        $this->assertTrue($orderBook['demo']);

        $this->assertEquals('EUR/USD', $orderBook['pair']);
        $this->assertCount(5, $orderBook['bids']);
        $this->assertCount(5, $orderBook['asks']);

        // Check bid prices are descending
        $prevPrice = PHP_FLOAT_MAX;
        foreach ($orderBook['bids'] as $bid) {
            $price = (float) $bid['price'];
            $this->assertLessThan($prevPrice, $price);
            $prevPrice = $price;
        }

        // Check ask prices are ascending
        $prevPrice = 0;
        foreach ($orderBook['asks'] as $ask) {
            $price = (float) $ask['price'];
            $this->assertGreaterThan($prevPrice, $price);
            $prevPrice = $price;
        }
    }

    /** @test */
    public function it_provides_market_data_with_realistic_values()
    {
        $marketData = $this->service->getMarketData('BTC', 'USD');

        $this->assertArrayHasKey('pair', $marketData);
        $this->assertArrayHasKey('last_price', $marketData);
        $this->assertArrayHasKey('bid', $marketData);
        $this->assertArrayHasKey('ask', $marketData);
        $this->assertArrayHasKey('volume_24h', $marketData);
        $this->assertArrayHasKey('change_24h', $marketData);
        $this->assertArrayHasKey('change_percentage_24h', $marketData);
        $this->assertArrayHasKey('high_24h', $marketData);
        $this->assertArrayHasKey('low_24h', $marketData);
        $this->assertTrue($marketData['demo']);

        $this->assertEquals('BTC/USD', $marketData['pair']);

        // Check bid < last_price < ask (spread)
        $bid = (float) $marketData['bid'];
        $ask = (float) $marketData['ask'];
        $lastPrice = (float) $marketData['last_price'];

        $this->assertLessThan($lastPrice, $bid);
        $this->assertGreaterThan($lastPrice, $ask);

        // Check high/low range
        $high = (float) $marketData['high_24h'];
        $low = (float) $marketData['low_24h'];

        $this->assertGreaterThan($low, $high);
        $this->assertLessThanOrEqual($high, $lastPrice);
        $this->assertGreaterThanOrEqual($low, $lastPrice);
    }

    /** @test */
    public function it_creates_trade_record_when_order_is_filled()
    {
        Event::fake();

        $orderData = [
            'user_id'        => 1,
            'account_id'     => 1,
            'type'           => 'market',
            'side'           => 'buy',
            'base_currency'  => 'ETH',
            'quote_currency' => 'USD',
            'amount'         => 2,
        ];

        $order = $this->service->placeOrder($orderData);

        // Check that a trade was created
        $trade = Trade::where('buy_order_id', $order->order_id)->first();

        $this->assertNotNull($trade);
        $this->assertStringStartsWith('demo_trd_', $trade->trade_id);
        $this->assertEquals($order->account_id, $trade->buyer_account_id);
        $this->assertEquals($order->account_id, $trade->seller_account_id);
        $this->assertEquals(2, $trade->amount);
        $this->assertTrue($trade->metadata['demo_mode']);
        $this->assertTrue($trade->metadata['instant_fill']);
    }

    /** @test */
    public function it_respects_auto_fill_configuration()
    {
        Config::set('demo.features.auto_fill_orders', false);
        Event::fake();

        $orderData = [
            'user_id'        => 1,
            'account_id'     => 1,
            'type'           => 'limit',
            'side'           => 'buy',
            'base_currency'  => 'EUR',
            'quote_currency' => 'USD',
            'amount'         => 100,
            'price'          => 1.08,
        ];

        $order = $this->service->placeOrder($orderData);

        $this->assertEquals('pending', $order->status);
        $this->assertEquals(0, $order->filled_amount);
        $this->assertNull($order->filled_at);

        Event::assertDispatched(OrderPlaced::class);
        Event::assertNotDispatched(OrderMatched::class);
    }

    /** @test */
    public function it_handles_unknown_currency_pairs_gracefully()
    {
        $orderData = [
            'user_id'        => 1,
            'account_id'     => 1,
            'type'           => 'market',
            'side'           => 'buy',
            'base_currency'  => 'XYZ', // Unknown currency
            'quote_currency' => 'ABC', // Unknown currency
            'amount'         => 10,
        ];

        $order = $this->service->placeOrder($orderData);

        // Should still create order with default price of 1.0
        $this->assertGreaterThan(0, $order->price);
    }
}
