<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Sagas;

use App\Domain\Exchange\Events\InventoryImbalanceDetected;
use App\Domain\Exchange\Events\LiquidityAdded;
use App\Domain\Exchange\Events\LiquidityRemoved;
use App\Domain\Exchange\Events\MarketVolatilityChanged;
use App\Domain\Exchange\Events\OrderExecuted;
use App\Domain\Exchange\Events\SpreadAdjusted;
use App\Domain\Exchange\Services\LiquidityPoolService;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\EventSourcing\EventHandlers\Reactors\Reactor;

/**
 * Saga for managing spreads in liquidity pools based on market conditions.
 * Monitors inventory levels, volatility, and liquidity depth to adjust spreads dynamically.
 */
class SpreadManagementSaga extends Reactor
{
    private const CACHE_PREFIX = 'spread_management:';

    private const CACHE_TTL = 300; // 5 minutes

    // Spread adjustment thresholds
    private const MIN_SPREAD_BPS = 10; // 0.1% minimum spread

    private const MAX_SPREAD_BPS = 500; // 5% maximum spread

    private const DEFAULT_SPREAD_BPS = 30; // 0.3% default spread

    // Inventory imbalance thresholds
    private const INVENTORY_IMBALANCE_THRESHOLD = 0.2; // 20% imbalance triggers adjustment

    private const CRITICAL_IMBALANCE_THRESHOLD = 0.4; // 40% imbalance triggers aggressive adjustment

    // Volatility thresholds
    private const HIGH_VOLATILITY_THRESHOLD = 0.05; // 5% price movement

    private const EXTREME_VOLATILITY_THRESHOLD = 0.1; // 10% price movement

    public function __construct(
        private readonly LiquidityPoolService $poolService,
    ) {
    }

    /**
     * Handle liquidity added event - recalculate optimal spread.
     */
    public function onLiquidityAdded(LiquidityAdded $event): void
    {
        $this->recalculateSpread($event->poolId, 'liquidity_added');
    }

    /**
     * Handle liquidity removed event - widen spread if liquidity is low.
     */
    public function onLiquidityRemoved(LiquidityRemoved $event): void
    {
        $this->recalculateSpread($event->poolId, 'liquidity_removed');
    }

    /**
     * Handle order executed - check for inventory imbalance.
     */
    public function onOrderExecuted(OrderExecuted $event): void
    {
        // Get pool associated with this trading pair
        $pool = $this->poolService->getPoolByPair(
            $event->baseCurrency,
            $event->quoteCurrency
        );

        if (! $pool) {
            return;
        }

        $this->checkInventoryBalance($pool->pool_id);
        $this->updateVolumeMetrics($pool->pool_id, $event->amount, $event->price);
    }

    /**
     * Recalculate optimal spread based on current market conditions.
     */
    private function recalculateSpread(string $poolId, string $trigger): void
    {
        try {
            $pool = $this->poolService->getPool($poolId);
            if (! $pool) {
                return;
            }

            // Get current metrics
            $metrics = $this->poolService->getPoolMetrics($poolId);
            $volatility = $this->calculateVolatility($poolId);
            $inventoryRatio = $this->calculateInventoryRatio($pool);
            $liquidityDepth = $this->calculateLiquidityDepth($pool);

            // Calculate base spread
            $baseSpread = self::DEFAULT_SPREAD_BPS;

            // Adjust for volatility
            if ($volatility > self::EXTREME_VOLATILITY_THRESHOLD) {
                $baseSpread *= 3; // Triple spread in extreme volatility
            } elseif ($volatility > self::HIGH_VOLATILITY_THRESHOLD) {
                $baseSpread *= 2; // Double spread in high volatility
            }

            // Adjust for inventory imbalance
            $imbalance = abs(0.5 - $inventoryRatio);
            if ($imbalance > self::CRITICAL_IMBALANCE_THRESHOLD) {
                $baseSpread *= 1.5; // Increase spread by 50% for critical imbalance

                // Record imbalance event
                event(new InventoryImbalanceDetected(
                    poolId: $poolId,
                    baseCurrencyRatio: $inventoryRatio,
                    severity: 'critical',
                    recommendedAction: 'rebalance_urgent'
                ));
            } elseif ($imbalance > self::INVENTORY_IMBALANCE_THRESHOLD) {
                $baseSpread *= 1.25; // Increase spread by 25% for moderate imbalance

                event(new InventoryImbalanceDetected(
                    poolId: $poolId,
                    baseCurrencyRatio: $inventoryRatio,
                    severity: 'moderate',
                    recommendedAction: 'monitor'
                ));
            }

            // Adjust for liquidity depth
            if ($liquidityDepth < 10000) { // Low liquidity
                $baseSpread *= 1.5;
            } elseif ($liquidityDepth < 50000) { // Medium liquidity
                $baseSpread *= 1.2;
            }

            // Apply bounds
            $finalSpread = max(self::MIN_SPREAD_BPS, min(self::MAX_SPREAD_BPS, $baseSpread));

            // Only adjust if spread changed significantly (>10%)
            $currentSpread = $this->getCurrentSpread($poolId);
            if (abs($finalSpread - $currentSpread) / $currentSpread > 0.1) {
                $this->adjustSpread($poolId, $finalSpread, $trigger);
            }

            // Cache the calculation
            $this->cacheSpreadData($poolId, [
                'spread'          => $finalSpread,
                'volatility'      => $volatility,
                'inventory_ratio' => $inventoryRatio,
                'liquidity_depth' => $liquidityDepth,
                'trigger'         => $trigger,
                'timestamp'       => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to recalculate spread', [
                'pool_id' => $poolId,
                'trigger' => $trigger,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check inventory balance and trigger rebalancing if needed.
     */
    private function checkInventoryBalance(string $poolId): void
    {
        $pool = $this->poolService->getPool($poolId);
        if (! $pool) {
            return;
        }

        $inventoryRatio = $this->calculateInventoryRatio($pool);
        $imbalance = abs(0.5 - $inventoryRatio);

        if ($imbalance > self::CRITICAL_IMBALANCE_THRESHOLD) {
            // Trigger automatic rebalancing
            $this->poolService->rebalancePool($poolId, '0.5');

            Log::warning('Critical inventory imbalance detected, triggering rebalance', [
                'pool_id'         => $poolId,
                'inventory_ratio' => $inventoryRatio,
                'imbalance'       => $imbalance,
            ]);
        }
    }

    /**
     * Calculate current volatility for the pool.
     */
    private function calculateVolatility(string $poolId): float
    {
        $cacheKey = self::CACHE_PREFIX . "volatility:{$poolId}";

        return (float) Cache::remember($cacheKey, 60, function () use ($poolId) {
            // Get recent price history
            $prices = $this->getRecentPrices($poolId, 100);

            if (count($prices) < 2) {
                return 0.0;
            }

            // Calculate standard deviation of returns
            $returns = [];
            for ($i = 1; $i < count($prices); $i++) {
                $returns[] = ($prices[$i] - $prices[$i - 1]) / $prices[$i - 1];
            }

            $mean = array_sum($returns) / count($returns);
            $variance = array_sum(array_map(fn ($r) => pow($r - $mean, 2), $returns)) / count($returns);

            return sqrt($variance);
        });
    }

    /**
     * Calculate inventory ratio (base currency / total value).
     */
    private function calculateInventoryRatio($pool): float
    {
        $totalValue = (float) $pool->base_reserve + (float) $pool->quote_reserve;

        if ($totalValue == 0) {
            return 0.5; // Default to balanced
        }

        return (float) $pool->base_reserve / $totalValue;
    }

    /**
     * Calculate liquidity depth (total value in pool).
     */
    private function calculateLiquidityDepth($pool): float
    {
        // Convert to USD equivalent for comparison
        $basePrice = $this->getAssetPrice($pool->base_currency);
        $quotePrice = $this->getAssetPrice($pool->quote_currency);

        return ($pool->base_reserve * $basePrice) + ($pool->quote_reserve * $quotePrice);
    }

    /**
     * Get current spread for the pool.
     */
    private function getCurrentSpread(string $poolId): float
    {
        $cacheKey = self::CACHE_PREFIX . "current_spread:{$poolId}";

        return (float) Cache::get($cacheKey, self::DEFAULT_SPREAD_BPS);
    }

    /**
     * Adjust the spread for the pool.
     */
    private function adjustSpread(string $poolId, float $newSpread, string $reason): void
    {
        // Update spread in cache
        $cacheKey = self::CACHE_PREFIX . "current_spread:{$poolId}";
        Cache::put($cacheKey, $newSpread, self::CACHE_TTL);

        // Record spread adjustment event
        event(new SpreadAdjusted(
            poolId: $poolId,
            oldSpread: $this->getCurrentSpread($poolId),
            newSpread: $newSpread,
            reason: $reason,
            timestamp: now()
        ));

        // Update pool parameters with spread in metadata
        $this->poolService->updatePoolParameters(
            $poolId,
            null,
            null,
            ['spread_bps' => $newSpread]
        );

        Log::info('Spread adjusted', [
            'pool_id'    => $poolId,
            'new_spread' => $newSpread,
            'reason'     => $reason,
        ]);
    }

    /**
     * Update volume metrics for the pool.
     */
    private function updateVolumeMetrics(string $poolId, float $amount, float $price): void
    {
        $cacheKey = self::CACHE_PREFIX . "volume:{$poolId}:" . now()->format('Y-m-d-H');

        $currentVolume = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentVolume + ($amount * $price), 3600);

        // Store price for volatility calculation
        $priceKey = self::CACHE_PREFIX . "prices:{$poolId}";
        $prices = Cache::get($priceKey, []);
        $prices[] = ['price' => $price, 'timestamp' => now()];

        // Keep only last 1000 prices
        if (count($prices) > 1000) {
            $prices = array_slice($prices, -1000);
        }

        Cache::put($priceKey, $prices, 3600);
    }

    /**
     * Get recent prices for volatility calculation.
     */
    private function getRecentPrices(string $poolId, int $limit): array
    {
        $priceKey = self::CACHE_PREFIX . "prices:{$poolId}";
        $prices = Cache::get($priceKey, []);

        // Extract price values
        $priceValues = array_map(fn ($p) => $p['price'], $prices);

        return array_slice($priceValues, -$limit);
    }

    /**
     * Get asset price in USD.
     */
    private function getAssetPrice(string $assetCode): float
    {
        // This would integrate with price oracle or exchange service
        // For now, return mock prices
        return match ($assetCode) {
            'BTC' => 50000,
            'ETH' => 3000,
            'USD', 'USDT', 'USDC' => 1,
            default => 100,
        };
    }

    /**
     * Cache spread calculation data.
     */
    private function cacheSpreadData(string $poolId, array $data): void
    {
        $cacheKey = self::CACHE_PREFIX . "data:{$poolId}";
        Cache::put($cacheKey, $data, self::CACHE_TTL);
    }

    /**
     * Handle market volatility change event.
     */
    public function onMarketVolatilityChanged(MarketVolatilityChanged $event): void
    {
        // Recalculate spreads for all affected pools
        $pools = $this->poolService->getActivePools();

        foreach ($pools as $pool) {
            if (
                $pool->base_currency === $event->assetCode ||
                $pool->quote_currency === $event->assetCode
            ) {
                $this->recalculateSpread($pool->pool_id, 'volatility_change');
            }
        }
    }
}
