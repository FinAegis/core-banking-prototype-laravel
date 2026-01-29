<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Exchange\Events\OrderMatched;
use App\Domain\Exchange\Services\Broadcast\OrderBookBroadcastService;
use App\Domain\Treasury\Services\Broadcast\PortfolioBroadcastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Event listener that bridges domain events to WebSocket broadcasts.
 *
 * This listener catches domain events and triggers appropriate
 * broadcast events for real-time client updates.
 */
class BroadcastEventListener implements ShouldQueue
{
    /**
     * The name of the queue connection to use.
     */
    public string $connection = 'redis';

    /**
     * The name of the queue to use.
     */
    public string $queue = 'broadcasts';

    public function __construct(
        private readonly OrderBookBroadcastService $orderBookBroadcastService,
        private readonly PortfolioBroadcastService $portfolioBroadcastService,
    ) {
    }

    /**
     * Handle the OrderMatched event.
     *
     * Triggers both order book and trade execution broadcasts.
     */
    public function handleOrderMatched(OrderMatched $event): void
    {
        if (! config('websocket.enabled', true)) {
            return;
        }

        $tenantId = $this->getTenantId();

        try {
            // Broadcast trade execution
            $this->orderBookBroadcastService->broadcastTradeExecuted(
                tenantId: $tenantId,
                tradeId: $event->tradeId,
                pair: $event->pair,
                side: $event->takerSide,
                price: $event->price,
                quantity: $event->quantity,
                total: bcmul($event->price, $event->quantity, 8),
                makerOrderId: $event->makerOrderId,
                takerOrderId: $event->takerOrderId,
                makerFee: $event->makerFee ?? '0',
                takerFee: $event->takerFee ?? '0',
            );

            Log::debug('Broadcast trade executed', [
                'trade_id'  => $event->tradeId,
                'pair'      => $event->pair,
                'tenant_id' => $tenantId,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to broadcast trade execution', [
                'trade_id' => $event->tradeId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the portfolio broadcast service instance.
     *
     * Exposed for external use when portfolio events need broadcasting.
     */
    public function getPortfolioBroadcastService(): PortfolioBroadcastService
    {
        return $this->portfolioBroadcastService;
    }

    /**
     * Get the list of events this listener handles.
     *
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            OrderMatched::class => 'handleOrderMatched',
        ];
    }

    /**
     * Get the current tenant ID.
     */
    private function getTenantId(): string
    {
        if (function_exists('tenant') && tenant()) {
            return (string) tenant()->getTenantKey();
        }

        return 'default';
    }
}
