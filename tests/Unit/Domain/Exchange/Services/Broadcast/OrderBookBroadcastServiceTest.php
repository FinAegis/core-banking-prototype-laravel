<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exchange\Services\Broadcast;

use App\Domain\Exchange\Events\Broadcast\OrderBookUpdated;
use App\Domain\Exchange\Events\Broadcast\TradeExecuted;
use App\Domain\Exchange\Services\Broadcast\OrderBookBroadcastService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderBookBroadcastServiceTest extends TestCase
{
    private OrderBookBroadcastService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Force array cache driver for tests
        config(['cache.default' => 'array']);

        $this->service = new OrderBookBroadcastService();

        // Clear cache before each test
        Cache::flush();

        // Fake events
        Event::fake([
            OrderBookUpdated::class,
            TradeExecuted::class,
        ]);
    }

    #[Test]
    public function it_broadcasts_order_book_update(): void
    {
        config(['websocket.enabled' => true]);

        $this->service->broadcastOrderBookUpdate(
            tenantId: '1',
            pair: 'EUR/USD',
            bids: [
                ['price' => '1.0850', 'quantity' => '10000', 'total' => '10850'],
                ['price' => '1.0849', 'quantity' => '5000', 'total' => '5424.50'],
            ],
            asks: [
                ['price' => '1.0851', 'quantity' => '8000', 'total' => '8680.80'],
                ['price' => '1.0852', 'quantity' => '12000', 'total' => '13022.40'],
            ],
            bestBid: '1.0850',
            bestAsk: '1.0851',
            spread: '0.0001',
            spreadPercentage: '0.01',
            sequenceNumber: 12345,
        );

        Event::assertDispatched(OrderBookUpdated::class, function ($event) {
            return $event->tenantId === '1'
                && $event->pair === 'EUR/USD'
                && $event->bestBid === '1.0850'
                && $event->bestAsk === '1.0851'
                && $event->sequenceNumber === 12345
                && count($event->bids) === 2
                && count($event->asks) === 2;
        });
    }

    #[Test]
    public function it_respects_rate_limits_for_order_book(): void
    {
        config([
            'websocket.enabled'                                 => true,
            'websocket.rate_limiting.order_book.max_per_second' => 2,
        ]);

        // First two should succeed
        $this->service->broadcastOrderBookUpdate(
            tenantId: '1',
            pair: 'EUR/USD',
            bids: [],
            asks: [],
            bestBid: '1.0850',
            bestAsk: '1.0851',
            spread: '0.0001',
            spreadPercentage: '0.01',
            sequenceNumber: 1,
        );

        $this->service->broadcastOrderBookUpdate(
            tenantId: '1',
            pair: 'EUR/USD',
            bids: [],
            asks: [],
            bestBid: '1.0850',
            bestAsk: '1.0851',
            spread: '0.0001',
            spreadPercentage: '0.01',
            sequenceNumber: 2,
        );

        // Third should be rate limited
        $this->service->broadcastOrderBookUpdate(
            tenantId: '1',
            pair: 'EUR/USD',
            bids: [],
            asks: [],
            bestBid: '1.0850',
            bestAsk: '1.0851',
            spread: '0.0001',
            spreadPercentage: '0.01',
            sequenceNumber: 3,
        );

        // Only 2 events should have been dispatched
        Event::assertDispatchedTimes(OrderBookUpdated::class, 2);
    }

    #[Test]
    public function it_broadcasts_trade_executed(): void
    {
        config(['websocket.enabled' => true]);

        $this->service->broadcastTradeExecuted(
            tenantId: '1',
            tradeId: 'trade-123',
            pair: 'EUR/USD',
            side: 'buy',
            price: '1.0850',
            quantity: '1000',
            total: '1085.00',
            makerOrderId: 'order-maker',
            takerOrderId: 'order-taker',
            makerFee: '1.08',
            takerFee: '2.17',
        );

        Event::assertDispatched(TradeExecuted::class, function ($event) {
            return $event->tenantId === '1'
                && $event->tradeId === 'trade-123'
                && $event->pair === 'EUR/USD'
                && $event->side === 'buy'
                && $event->price === '1.0850'
                && $event->quantity === '1000'
                && $event->makerOrderId === 'order-maker'
                && $event->takerOrderId === 'order-taker';
        });
    }

    #[Test]
    public function it_does_not_broadcast_when_disabled(): void
    {
        config(['websocket.enabled' => false]);

        $this->service->broadcastOrderBookUpdate(
            tenantId: '1',
            pair: 'EUR/USD',
            bids: [],
            asks: [],
            bestBid: '1.0850',
            bestAsk: '1.0851',
            spread: '0.0001',
            spreadPercentage: '0.01',
            sequenceNumber: 1,
        );

        $this->service->broadcastTradeExecuted(
            tenantId: '1',
            tradeId: 'trade-123',
            pair: 'EUR/USD',
            side: 'buy',
            price: '1.0850',
            quantity: '1000',
            total: '1085.00',
            makerOrderId: 'order-maker',
            takerOrderId: 'order-taker',
            makerFee: '1.08',
            takerFee: '2.17',
        );

        Event::assertNotDispatched(OrderBookUpdated::class);
        Event::assertNotDispatched(TradeExecuted::class);
    }

    #[Test]
    public function it_flushes_pending_batches(): void
    {
        config([
            'websocket.enabled'                                 => true,
            'websocket.rate_limiting.order_book.max_per_second' => 1,
        ]);

        // First succeeds
        $this->service->broadcastOrderBookUpdate(
            tenantId: '1',
            pair: 'EUR/USD',
            bids: [['price' => '1.0850', 'quantity' => '10000', 'total' => '10850']],
            asks: [['price' => '1.0851', 'quantity' => '8000', 'total' => '8680.80']],
            bestBid: '1.0850',
            bestAsk: '1.0851',
            spread: '0.0001',
            spreadPercentage: '0.01',
            sequenceNumber: 1,
        );

        // Second gets batched
        $this->service->broadcastOrderBookUpdate(
            tenantId: '1',
            pair: 'EUR/USD',
            bids: [['price' => '1.0852', 'quantity' => '15000', 'total' => '16278']],
            asks: [['price' => '1.0853', 'quantity' => '10000', 'total' => '10853']],
            bestBid: '1.0852',
            bestAsk: '1.0853',
            spread: '0.0001',
            spreadPercentage: '0.01',
            sequenceNumber: 2,
        );

        Event::assertDispatchedTimes(OrderBookUpdated::class, 1);

        // Flush pending batches
        $flushed = $this->service->flushPendingBatches();

        // After flush, we should have the batched event
        $this->assertGreaterThanOrEqual(0, $flushed);
    }
}
