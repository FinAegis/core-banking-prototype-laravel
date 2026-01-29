<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events\Broadcast;

use App\Broadcasting\TenantBroadcastEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for trade executions.
 *
 * Fired when a trade is executed in the exchange.
 * Includes maker/taker information and fee details.
 */
class TradeExecuted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    use TenantBroadcastEvent;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $tradeId,
        public readonly string $pair,
        public readonly string $side,
        public readonly string $price,
        public readonly string $quantity,
        public readonly string $total,
        public readonly string $makerOrderId,
        public readonly string $takerOrderId,
        public readonly string $makerFee,
        public readonly string $takerFee,
        public readonly string $timestamp,
    ) {
    }

    protected function tenantChannelSuffix(): string
    {
        return 'exchange';
    }

    public function broadcastAs(): string
    {
        return 'trade.executed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'trade_id'       => $this->tradeId,
            'pair'           => $this->pair,
            'side'           => $this->side,
            'price'          => $this->price,
            'quantity'       => $this->quantity,
            'total'          => $this->total,
            'maker_order_id' => $this->makerOrderId,
            'taker_order_id' => $this->takerOrderId,
            'maker_fee'      => $this->makerFee,
            'taker_fee'      => $this->takerFee,
            'timestamp'      => $this->timestamp,
        ];
    }

    public function broadcastWhen(): bool
    {
        return config('websocket.enabled', true);
    }

    public function broadcastConnection(): string
    {
        return config('websocket.queue.connection', 'redis');
    }

    public function broadcastQueue(): string
    {
        return config('websocket.queue.name', 'broadcasts');
    }
}
