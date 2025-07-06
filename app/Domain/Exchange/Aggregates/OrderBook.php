<?php

namespace App\Domain\Exchange\Aggregates;

use App\Domain\Exchange\Events\OrderBookInitialized;
use App\Domain\Exchange\Events\OrderAddedToBook;
use App\Domain\Exchange\Events\OrderRemovedFromBook;
use App\Domain\Exchange\Events\OrderBookSnapshotTaken;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

class OrderBook extends AggregateRoot
{
    protected string $orderBookId;
    protected string $baseCurrency;
    protected string $quoteCurrency;
    protected Collection $buyOrders;
    protected Collection $sellOrders;
    protected ?BigDecimal $lastPrice = null;
    protected ?BigDecimal $bestBid = null;
    protected ?BigDecimal $bestAsk = null;

    public function __construct()
    {
        parent::__construct();
        $this->buyOrders = collect();
        $this->sellOrders = collect();
    }

    public function initialize(
        string $orderBookId,
        string $baseCurrency,
        string $quoteCurrency,
        array $metadata = []
    ): self {
        $this->recordThat(new OrderBookInitialized(
            orderBookId: $orderBookId,
            baseCurrency: $baseCurrency,
            quoteCurrency: $quoteCurrency,
            metadata: $metadata
        ));

        return $this;
    }

    public function addOrder(
        string $orderId,
        string $type,
        string $price,
        string $amount,
        array $metadata = []
    ): self {
        $this->recordThat(new OrderAddedToBook(
            orderBookId: $this->orderBookId,
            orderId: $orderId,
            type: $type,
            price: $price,
            amount: $amount,
            metadata: $metadata
        ));

        return $this;
    }

    public function removeOrder(string $orderId, string $reason, array $metadata = []): self
    {
        $this->recordThat(new OrderRemovedFromBook(
            orderBookId: $this->orderBookId,
            orderId: $orderId,
            reason: $reason,
            metadata: $metadata
        ));

        return $this;
    }

    public function takeSnapshot(array $metadata = []): self
    {
        $this->recordThat(new OrderBookSnapshotTaken(
            orderBookId: $this->orderBookId,
            buyOrders: $this->buyOrders->toArray(),
            sellOrders: $this->sellOrders->toArray(),
            bestBid: $this->bestBid?->__toString(),
            bestAsk: $this->bestAsk?->__toString(),
            lastPrice: $this->lastPrice?->__toString(),
            metadata: $metadata
        ));

        return $this;
    }

    protected function applyOrderBookInitialized(OrderBookInitialized $event): void
    {
        $this->orderBookId = $event->orderBookId;
        $this->baseCurrency = $event->baseCurrency;
        $this->quoteCurrency = $event->quoteCurrency;
        $this->buyOrders = collect();
        $this->sellOrders = collect();
    }

    protected function applyOrderAddedToBook(OrderAddedToBook $event): void
    {
        $order = [
            'orderId' => $event->orderId,
            'price' => BigDecimal::of($event->price),
            'amount' => BigDecimal::of($event->amount),
            'timestamp' => now(),
        ];

        if ($event->type === 'buy') {
            $this->buyOrders->push($order);
            $this->buyOrders = $this->buyOrders->sortByDesc(function ($order) {
                return $order['price']->__toString();
            })->values();
            
            $this->bestBid = $this->buyOrders->first()['price'] ?? null;
        } else {
            $this->sellOrders->push($order);
            $this->sellOrders = $this->sellOrders->sortBy(function ($order) {
                return $order['price']->__toString();
            })->values();
            
            $this->bestAsk = $this->sellOrders->first()['price'] ?? null;
        }
    }

    protected function applyOrderRemovedFromBook(OrderRemovedFromBook $event): void
    {
        $this->buyOrders = $this->buyOrders->reject(function ($order) use ($event) {
            return $order['orderId'] === $event->orderId;
        })->values();

        $this->sellOrders = $this->sellOrders->reject(function ($order) use ($event) {
            return $order['orderId'] === $event->orderId;
        })->values();

        $this->bestBid = $this->buyOrders->first()['price'] ?? null;
        $this->bestAsk = $this->sellOrders->first()['price'] ?? null;
    }

    protected function applyOrderBookSnapshotTaken(OrderBookSnapshotTaken $event): void
    {
        // Snapshot is for auditing purposes
    }

    public function getOrderBookId(): string
    {
        return $this->orderBookId;
    }

    public function getBestBid(): ?BigDecimal
    {
        return $this->bestBid;
    }

    public function getBestAsk(): ?BigDecimal
    {
        return $this->bestAsk;
    }

    public function getSpread(): ?BigDecimal
    {
        if ($this->bestBid === null || $this->bestAsk === null) {
            return null;
        }

        return $this->bestAsk->minus($this->bestBid);
    }

    public function getBuyOrders(): Collection
    {
        return $this->buyOrders;
    }

    public function getSellOrders(): Collection
    {
        return $this->sellOrders;
    }
    
    public static function generateId(string $baseAsset, string $quoteAsset): string
    {
        return sprintf('orderbook-%s-%s', strtolower($baseAsset), strtolower($quoteAsset));
    }
}