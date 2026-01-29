<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Broadcast;

use App\Broadcasting\TenantBroadcastEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for portfolio value updates.
 *
 * Fired when an asset price changes affecting portfolio valuation.
 * Includes breakdown by asset and performance metrics.
 */
class PortfolioValueUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    use TenantBroadcastEvent;

    /**
     * @param  array<string, array{quantity: string, price: string, value: string, allocation: string}>  $holdings
     * @param  array<string, string>  $performance
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $portfolioId,
        public readonly string $portfolioName,
        public readonly string $totalValue,
        public readonly string $previousValue,
        public readonly string $changeAmount,
        public readonly string $changePercentage,
        public readonly string $currency,
        public readonly array $holdings,
        public readonly array $performance,
        public readonly string $timestamp,
    ) {
    }

    protected function tenantChannelSuffix(): string
    {
        return 'accounts';
    }

    public function broadcastAs(): string
    {
        return 'portfolio.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'portfolio_id'      => $this->portfolioId,
            'portfolio_name'    => $this->portfolioName,
            'total_value'       => $this->totalValue,
            'previous_value'    => $this->previousValue,
            'change_amount'     => $this->changeAmount,
            'change_percentage' => $this->changePercentage,
            'currency'          => $this->currency,
            'holdings'          => $this->holdings,
            'performance'       => $this->performance,
            'timestamp'         => $this->timestamp,
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
