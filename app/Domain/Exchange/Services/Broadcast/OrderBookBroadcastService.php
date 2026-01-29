<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Services\Broadcast;

use App\Domain\Exchange\Events\Broadcast\OrderBookUpdated;
use App\Domain\Exchange\Events\Broadcast\TradeExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for broadcasting order book updates with throttling.
 *
 * Implements rate limiting to prevent flooding clients with updates
 * while ensuring timely delivery of market data.
 */
class OrderBookBroadcastService
{
    private const CACHE_PREFIX = 'ws:orderbook:';

    private const TRADE_CACHE_PREFIX = 'ws:trade:';

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $pendingBatches = [];

    /**
     * Broadcast an order book update with rate limiting.
     *
     * @param  array<int, array{price: string, quantity: string, total: string}>  $bids
     * @param  array<int, array{price: string, quantity: string, total: string}>  $asks
     */
    public function broadcastOrderBookUpdate(
        string $tenantId,
        string $pair,
        array $bids,
        array $asks,
        string $bestBid,
        string $bestAsk,
        string $spread,
        string $spreadPercentage,
        int $sequenceNumber,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $cacheKey = self::CACHE_PREFIX . "{$tenantId}:{$pair}";
        $config = config('websocket.rate_limiting.order_book');
        $maxPerSecond = $config['max_per_second'] ?? 10;
        $batchWindowMs = $config['batch_window_ms'] ?? 100;

        // Check rate limit
        if (! $this->checkRateLimit($cacheKey, $maxPerSecond)) {
            // Add to pending batch for next broadcast window
            $this->addToPendingBatch($cacheKey, [
                'bids'              => $bids,
                'asks'              => $asks,
                'best_bid'          => $bestBid,
                'best_ask'          => $bestAsk,
                'spread'            => $spread,
                'spread_percentage' => $spreadPercentage,
                'sequence'          => $sequenceNumber,
            ]);

            return;
        }

        // Broadcast immediately
        $this->dispatchOrderBookEvent(
            $tenantId,
            $pair,
            $bids,
            $asks,
            $bestBid,
            $bestAsk,
            $spread,
            $spreadPercentage,
            count($bids),
            count($asks),
            $sequenceNumber,
        );

        // Increment rate limit counter
        $this->incrementRateLimit($cacheKey);
    }

    /**
     * Broadcast a trade execution.
     */
    public function broadcastTradeExecuted(
        string $tenantId,
        string $tradeId,
        string $pair,
        string $side,
        string $price,
        string $quantity,
        string $total,
        string $makerOrderId,
        string $takerOrderId,
        string $makerFee,
        string $takerFee,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $cacheKey = self::TRADE_CACHE_PREFIX . "{$tenantId}:{$pair}";
        $config = config('websocket.rate_limiting.trades');
        $maxPerSecond = $config['max_per_second'] ?? 50;

        // Check rate limit (trades are more important, less strict batching)
        if (! $this->checkRateLimit($cacheKey, $maxPerSecond)) {
            Log::debug('Trade broadcast rate limited', [
                'tenant_id' => $tenantId,
                'trade_id'  => $tradeId,
                'pair'      => $pair,
            ]);

            return;
        }

        event(new TradeExecuted(
            tenantId: $tenantId,
            tradeId: $tradeId,
            pair: $pair,
            side: $side,
            price: $price,
            quantity: $quantity,
            total: $total,
            makerOrderId: $makerOrderId,
            takerOrderId: $takerOrderId,
            makerFee: $makerFee,
            takerFee: $takerFee,
            timestamp: now()->toIso8601String(),
        ));

        $this->incrementRateLimit($cacheKey);
    }

    /**
     * Flush any pending batched updates.
     * Should be called periodically via scheduler or queue worker.
     */
    public function flushPendingBatches(): int
    {
        $flushed = 0;

        foreach ($this->pendingBatches as $cacheKey => $updates) {
            if (empty($updates)) {
                continue;
            }

            // Get the latest update (most recent data wins)
            $latest = end($updates);

            // Parse tenant and pair from cache key
            $parts = explode(':', str_replace(self::CACHE_PREFIX, '', $cacheKey));
            if (count($parts) < 2) {
                continue;
            }

            [$tenantId, $pair] = $parts;

            $this->dispatchOrderBookEvent(
                $tenantId,
                $pair,
                $latest['bids'],
                $latest['asks'],
                $latest['best_bid'],
                $latest['best_ask'],
                $latest['spread'],
                $latest['spread_percentage'],
                count($latest['bids']),
                count($latest['asks']),
                $latest['sequence'],
            );

            $flushed++;
        }

        $this->pendingBatches = [];

        return $flushed;
    }

    /**
     * Check if WebSocket broadcasting is enabled.
     */
    private function isEnabled(): bool
    {
        return (bool) config('websocket.enabled', true);
    }

    /**
     * Check if we're within rate limit.
     */
    private function checkRateLimit(string $cacheKey, int $maxPerSecond): bool
    {
        $count = (int) Cache::get($cacheKey . ':count', 0);

        return $count < $maxPerSecond;
    }

    /**
     * Increment the rate limit counter.
     */
    private function incrementRateLimit(string $cacheKey): void
    {
        $countKey = $cacheKey . ':count';

        if (! Cache::has($countKey)) {
            Cache::put($countKey, 1, 1); // Expires in 1 second
        } else {
            Cache::increment($countKey);
        }
    }

    /**
     * Add update to pending batch.
     *
     * @param  array<string, mixed>  $data
     */
    private function addToPendingBatch(string $cacheKey, array $data): void
    {
        if (! isset($this->pendingBatches[$cacheKey])) {
            $this->pendingBatches[$cacheKey] = [];
        }

        $this->pendingBatches[$cacheKey][] = $data;

        // Limit batch size to prevent memory issues
        /** @var int $maxBatchSize */
        $maxBatchSize = config('websocket.batching.max_batch_size', 100);
        if (count($this->pendingBatches[$cacheKey]) > $maxBatchSize) {
            $this->pendingBatches[$cacheKey] = array_slice(
                $this->pendingBatches[$cacheKey],
                -$maxBatchSize
            );
        }
    }

    /**
     * Dispatch the order book event.
     *
     * @param  array<int, array{price: string, quantity: string, total: string}>  $bids
     * @param  array<int, array{price: string, quantity: string, total: string}>  $asks
     */
    private function dispatchOrderBookEvent(
        string $tenantId,
        string $pair,
        array $bids,
        array $asks,
        string $bestBid,
        string $bestAsk,
        string $spread,
        string $spreadPercentage,
        int $bidCount,
        int $askCount,
        int $sequenceNumber,
    ): void {
        event(new OrderBookUpdated(
            tenantId: $tenantId,
            pair: $pair,
            bids: $bids,
            asks: $asks,
            bestBid: $bestBid,
            bestAsk: $bestAsk,
            spread: $spread,
            spreadPercentage: $spreadPercentage,
            bidCount: $bidCount,
            askCount: $askCount,
            timestamp: now()->toIso8601String(),
            sequenceNumber: $sequenceNumber,
        ));
    }
}
