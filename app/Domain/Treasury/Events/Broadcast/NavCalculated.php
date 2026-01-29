<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Broadcast;

use App\Broadcasting\TenantBroadcastEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for NAV (Net Asset Value) calculations.
 *
 * Fired when a portfolio's NAV is recalculated.
 * Includes per-share value and total assets/liabilities.
 */
class NavCalculated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    use TenantBroadcastEvent;

    /**
     * @param  array<string, string>  $breakdown
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $portfolioId,
        public readonly string $portfolioName,
        public readonly string $nav,
        public readonly string $previousNav,
        public readonly string $changeAmount,
        public readonly string $changePercentage,
        public readonly string $navPerShare,
        public readonly string $totalShares,
        public readonly string $totalAssets,
        public readonly string $totalLiabilities,
        public readonly string $currency,
        public readonly array $breakdown,
        public readonly string $calculatedAt,
    ) {
    }

    protected function tenantChannelSuffix(): string
    {
        return 'accounts';
    }

    public function broadcastAs(): string
    {
        return 'nav.calculated';
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
            'nav'               => $this->nav,
            'previous_nav'      => $this->previousNav,
            'change_amount'     => $this->changeAmount,
            'change_percentage' => $this->changePercentage,
            'nav_per_share'     => $this->navPerShare,
            'total_shares'      => $this->totalShares,
            'total_assets'      => $this->totalAssets,
            'total_liabilities' => $this->totalLiabilities,
            'currency'          => $this->currency,
            'breakdown'         => $this->breakdown,
            'calculated_at'     => $this->calculatedAt,
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
