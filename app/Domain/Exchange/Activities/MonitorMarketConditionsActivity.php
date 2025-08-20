<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Activities;

use App\Domain\Exchange\Services\ExchangeService;
use App\Domain\Exchange\Services\LiquidityPoolService;
use Illuminate\Support\Facades\Cache;
use Workflow\Activity\ActivityInterface;
use Workflow\Activity\ActivityMethod;

#[ActivityInterface]
class MonitorMarketConditionsActivity
{
    public function __construct(
        private readonly LiquidityPoolService $poolService,
        private readonly ExchangeService $exchangeService,
    ) {
    }

    /**
     * Monitor and analyze current market conditions.
     *
     * @param string $poolId Pool ID to monitor
     * @return array Market conditions including price, volume, volatility, inventory
     */
    #[ActivityMethod]
    public function execute(string $poolId): array
    {
        $pool = $this->poolService->getPool($poolId);
        $metrics = $this->poolService->getPoolMetrics($poolId);

        // Get order book depth
        $orderBook = $this->exchangeService->getOrderBook(
            $pool->base_currency,
            $pool->quote_currency
        );

        // Calculate mid price
        $bestBid = $orderBook['bids'][0]['price'] ?? 0;
        $bestAsk = $orderBook['asks'][0]['price'] ?? PHP_FLOAT_MAX;
        $midPrice = ($bestBid + $bestAsk) / 2;

        // If no orders, use pool price
        if ($midPrice == PHP_FLOAT_MAX / 2) {
            $midPrice = (float) $pool->quote_reserve / (float) $pool->base_reserve;
        }

        // Calculate volatility from recent price history
        $volatility = $this->calculateVolatility($poolId);

        // Get current inventory
        $inventory = $this->getCurrentInventory($poolId);

        // Calculate 24h volume
        $volume24h = $this->get24hVolume($poolId);

        // Calculate order book imbalance
        $bidVolume = array_sum(array_column($orderBook['bids'], 'amount'));
        $askVolume = array_sum(array_column($orderBook['asks'], 'amount'));
        $orderImbalance = $bidVolume > 0 ? ($bidVolume - $askVolume) / ($bidVolume + $askVolume) : 0;

        // Calculate spread
        $spread = $bestAsk > $bestBid ? ($bestAsk - $bestBid) / $midPrice * 10000 : 0; // in bps

        // Get PnL if available
        $pnl = Cache::get("market_maker:pnl:{$poolId}", 0);

        return [
            'mid_price'       => $midPrice,
            'best_bid'        => $bestBid,
            'best_ask'        => $bestAsk,
            'spread'          => $spread,
            'volatility'      => $volatility,
            'volume_24h'      => $volume24h,
            'inventory'       => $inventory,
            'order_imbalance' => $orderImbalance,
            'pool_tvl'        => $metrics['tvl'] ?? 0,
            'pool_apy'        => $metrics['apy_7d'] ?? 0,
            'pnl'             => $pnl,
            'timestamp'       => now(),
        ];
    }

    /**
     * Calculate recent price volatility.
     */
    private function calculateVolatility(string $poolId): float
    {
        $cacheKey = "market:volatility:{$poolId}";

        return Cache::remember($cacheKey, 60, function () use ($poolId) {
            $prices = Cache::get("market:prices:{$poolId}", []);

            if (count($prices) < 2) {
                return 0.0;
            }

            // Calculate returns
            $returns = [];
            for ($i = 1; $i < count($prices); $i++) {
                if ($prices[$i - 1] > 0) {
                    $returns[] = ($prices[$i] - $prices[$i - 1]) / $prices[$i - 1];
                }
            }

            if (empty($returns)) {
                return 0.0;
            }

            // Calculate standard deviation
            $mean = array_sum($returns) / count($returns);
            $variance = array_sum(array_map(fn ($r) => pow($r - $mean, 2), $returns)) / count($returns);

            return sqrt($variance);
        });
    }

    /**
     * Get current inventory levels.
     */
    private function getCurrentInventory(string $poolId): array
    {
        $pool = $this->poolService->getPool($poolId);

        // This would normally get actual wallet balances
        // For now, use pool reserves as proxy
        return [
            $pool->base_currency  => (float) $pool->base_reserve * 0.1, // Assume 10% of pool is market maker inventory
            $pool->quote_currency => (float) $pool->quote_reserve * 0.1,
        ];
    }

    /**
     * Get 24-hour trading volume.
     */
    private function get24hVolume(string $poolId): float
    {
        $volume = 0;

        // Sum hourly volumes for last 24 hours
        for ($i = 0; $i < 24; $i++) {
            $hour = now()->subHours($i)->format('Y-m-d-H');
            $cacheKey = "market:volume:{$poolId}:{$hour}";
            $volume += Cache::get($cacheKey, 0);
        }

        return $volume;
    }
}
