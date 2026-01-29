<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events\Broadcast;

use App\Broadcasting\TenantBroadcastEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for order book updates.
 *
 * Fired when the order book for a trading pair changes due to
 * new orders, cancellations, or fills.
 */
class OrderBookUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    use TenantBroadcastEvent;

    /**
     * @param  array<int, array{price: string, quantity: string, total: string}>  $bids
     * @param  array<int, array{price: string, quantity: string, total: string}>  $asks
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $pair,
        public readonly array $bids,
        public readonly array $asks,
        public readonly string $bestBid,
        public readonly string $bestAsk,
        public readonly string $spread,
        public readonly string $spreadPercentage,
        public readonly int $bidCount,
        public readonly int $askCount,
        public readonly string $timestamp,
        public readonly int $sequenceNumber,
    ) {
    }

    protected function tenantChannelSuffix(): string
    {
        return 'exchange';
    }

    public function broadcastAs(): string
    {
        return 'orderbook.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'pair'              => $this->pair,
            'bids'              => $this->bids,
            'asks'              => $this->asks,
            'best_bid'          => $this->bestBid,
            'best_ask'          => $this->bestAsk,
            'spread'            => $this->spread,
            'spread_percentage' => $this->spreadPercentage,
            'bid_count'         => $this->bidCount,
            'ask_count'         => $this->askCount,
            'timestamp'         => $this->timestamp,
            'sequence'          => $this->sequenceNumber,
        ];
    }

    /**
     * Determine if the event should be broadcast.
     */
    public function broadcastWhen(): bool
    {
        return config('websocket.enabled', true);
    }

    /**
     * Get the queue connection for the broadcast.
     */
    public function broadcastConnection(): string
    {
        return config('websocket.queue.connection', 'redis');
    }

    /**
     * Get the queue name for the broadcast.
     */
    public function broadcastQueue(): string
    {
        return config('websocket.queue.name', 'broadcasts');
    }
}
