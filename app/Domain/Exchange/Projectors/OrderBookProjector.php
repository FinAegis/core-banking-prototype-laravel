<?php

namespace App\Domain\Exchange\Projectors;

use App\Domain\Exchange\Events\OrderAddedToBook;
use App\Domain\Exchange\Events\OrderBookInitialized;
use App\Domain\Exchange\Events\OrderBookSnapshotTaken;
use App\Domain\Exchange\Events\OrderMatched;
use App\Domain\Exchange\Events\OrderRemovedFromBook;
use App\Domain\Exchange\Projections\OrderBook;
use App\Domain\Exchange\Projections\Trade;
use Brick\Math\BigDecimal;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class OrderBookProjector extends Projector
{
    public function onOrderBookInitialized(OrderBookInitialized $event): void
    {
        OrderBook::create([
            'order_book_id' => $event->orderBookId,
            'base_currency' => $event->baseCurrency,
            'quote_currency' => $event->quoteCurrency,
            'buy_orders' => json_encode([]),
            'sell_orders' => json_encode([]),
            'metadata' => $event->metadata ? json_encode($event->metadata) : null,
        ]);
    }

    public function onOrderAddedToBook(OrderAddedToBook $event): void
    {
        $orderBook = OrderBook::where('order_book_id', $event->orderBookId)->firstOrFail();
        
        $order = [
            'order_id' => $event->orderId,
            'price' => $event->price,
            'amount' => $event->amount,
            'timestamp' => now()->toIso8601String(),
        ];
        
        if ($event->type === 'buy') {
            $buyOrders = collect($orderBook->buy_orders ?? []);
            $buyOrders->push($order);
            
            // Sort buy orders by price descending (highest first)
            $buyOrders = $buyOrders->sortByDesc('price')->values();
            
            $orderBook->update([
                'buy_orders' => $buyOrders->toArray(),
                'best_bid' => $buyOrders->first()['price'] ?? null,
            ]);
        } else {
            $sellOrders = collect($orderBook->sell_orders ?? []);
            $sellOrders->push($order);
            
            // Sort sell orders by price ascending (lowest first)
            $sellOrders = $sellOrders->sortBy('price')->values();
            
            $orderBook->update([
                'sell_orders' => $sellOrders->toArray(),
                'best_ask' => $sellOrders->first()['price'] ?? null,
            ]);
        }
    }

    public function onOrderRemovedFromBook(OrderRemovedFromBook $event): void
    {
        $orderBook = OrderBook::where('order_book_id', $event->orderBookId)->firstOrFail();
        
        $buyOrders = collect($orderBook->buy_orders ?? [])->reject(function ($order) use ($event) {
            return $order['order_id'] === $event->orderId;
        })->values();
        
        $sellOrders = collect($orderBook->sell_orders ?? [])->reject(function ($order) use ($event) {
            return $order['order_id'] === $event->orderId;
        })->values();
        
        $orderBook->update([
            'buy_orders' => $buyOrders->toArray(),
            'sell_orders' => $sellOrders->toArray(),
            'best_bid' => $buyOrders->first()['price'] ?? null,
            'best_ask' => $sellOrders->first()['price'] ?? null,
        ]);
    }

    public function onOrderMatched(OrderMatched $event): void
    {
        // Update order book metrics after a trade
        $trade = Trade::where('trade_id', $event->tradeId)->first();
        
        if (!$trade) {
            return;
        }
        
        $orderBook = OrderBook::forPair($trade->base_currency, $trade->quote_currency)->first();
        
        if (!$orderBook) {
            return;
        }
        
        // Update last price
        $orderBook->last_price = $trade->price;
        
        // Update 24h volume
        $volume24h = Trade::forPair($trade->base_currency, $trade->quote_currency)
            ->recent(24)
            ->sum('amount');
        
        $orderBook->volume_24h = $volume24h;
        
        // Update 24h high/low
        $trades24h = Trade::forPair($trade->base_currency, $trade->quote_currency)
            ->recent(24)
            ->get();
        
        if ($trades24h->isNotEmpty()) {
            $orderBook->high_24h = $trades24h->max('price');
            $orderBook->low_24h = $trades24h->min('price');
            
            // Store open price for change calculation
            $oldestTrade = $trades24h->sortBy('created_at')->first();
            $metadata = $orderBook->metadata ?? [];
            $metadata['open_24h'] = $oldestTrade->price;
            $orderBook->metadata = $metadata;
        }
        
        $orderBook->save();
    }

    public function onOrderBookSnapshotTaken(OrderBookSnapshotTaken $event): void
    {
        // Snapshots are for auditing purposes
        // Could store in a separate snapshot table if needed
    }
}