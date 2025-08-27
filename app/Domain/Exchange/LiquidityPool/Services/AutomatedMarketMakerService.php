<?php

namespace App\Domain\Exchange\LiquidityPool\Services;

use App\Domain\Exchange\Contracts\PriceAggregatorInterface;
use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Projections\OrderBook;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DB;

class AutomatedMarketMakerService
{
    // Spread percentages for different market conditions
    private const TIGHT_SPREAD = '0.001'; // 0.1%

    private const NORMAL_SPREAD = '0.003'; // 0.3%

    private const WIDE_SPREAD = '0.005'; // 0.5%

    private const VOLATILE_SPREAD = '0.01'; // 1%

    // Order depth levels
    private const DEPTH_LEVELS = 5;

    private const DEPTH_INCREMENT = '0.002'; // 0.2% price increment per level

    public function __construct(
        private readonly PriceAggregatorInterface $priceAggregator
    ) {
    }

    /**
     * Generate market making orders for a liquidity pool.
     */
    public function generateMarketMakingOrders(string $poolId): array
    {
        $pool = LiquidityPool::where('pool_id', $poolId)->firstOrFail();

        // Get current market conditions
        $marketConditions = $this->analyzeMarketConditions($pool);
        $spread = $this->determineOptimalSpread($marketConditions);

        // Calculate mid price from pool reserves
        $midPrice = $this->calculateMidPrice($pool);

        // Generate buy and sell orders
        $orders = [];
        $orders = array_merge($orders, $this->generateBuyOrders($pool, $midPrice, $spread));
        $orders = array_merge($orders, $this->generateSellOrders($pool, $midPrice, $spread));

        return $orders;
    }

    /**
     * Analyze market conditions to determine optimal market making strategy.
     */
    private function analyzeMarketConditions(LiquidityPool $pool): array
    {
        // Get external market data
        $symbol = $pool->base_currency . '/' . $pool->quote_currency;
        $priceData = $this->priceAggregator->getAggregatedPrice($symbol);
        $externalPrice = BigDecimal::of($priceData['price'] ?? '0');

        // Calculate pool price
        $poolPrice = BigDecimal::of($pool->quote_reserve)
            ->dividedBy($pool->base_reserve, 18, RoundingMode::HALF_UP);

        // Calculate price deviation
        $priceDeviation = $externalPrice->isZero()
            ? BigDecimal::zero()
            : $poolPrice->minus($externalPrice)
            ->abs()
            ->dividedBy($externalPrice, 18, RoundingMode::HALF_UP);

        // Get recent volatility
        $volatility = $this->calculateRecentVolatility($pool);

        // Check order book depth
        $orderBook = OrderBook::where('base_currency', $pool->base_currency)
            ->where('quote_currency', $pool->quote_currency)
            ->first();

        if ($this !== null) {
            $bookDepth = $this->calculateOrderBookDepth($orderBook);
        }

        return [
            'pool_price'      => $poolPrice->__toString(),
            'external_price'  => $externalPrice->__toString(),
            'price_deviation' => $priceDeviation->__toString(),
            'volatility'      => $volatility->__toString(),
            'book_depth'      => $bookDepth,
            'volume_24h'      => $pool->volume_24h,
            'liquidity_depth' => $this->calculateLiquidityDepth($pool),
        ];
    }

    /**
     * Determine optimal spread based on market conditions.
     */
    private function determineOptimalSpread(array $marketConditions): string
    {
        $deviation = BigDecimal::of($marketConditions['price_deviation']);
        $volatility = BigDecimal::of($marketConditions['volatility']);

        // High volatility or price deviation = wider spread
        if ($volatility->isGreaterThan('0.05') || $deviation->isGreaterThan('0.02')) {
            return self::VOLATILE_SPREAD;
        }

        // Low liquidity depth = wider spread
        if ($marketConditions['liquidity_depth'] < 0.1) {
            return self::WIDE_SPREAD;
        }

        // Normal market conditions
        if ($volatility->isLessThan('0.02') && $deviation->isLessThan('0.005')) {
            return self::TIGHT_SPREAD;
        }

        return self::NORMAL_SPREAD;
    }

    /**
     * Generate buy orders at different price levels.
     */
    private function generateBuyOrders(
        LiquidityPool $pool,
        BigDecimal $midPrice,
        string $spread
    ): array {
        $orders = [];
        $spreadDecimal = BigDecimal::of($spread);
        $incrementDecimal = BigDecimal::of(self::DEPTH_INCREMENT);

        // Calculate available quote currency for market making
        $availableQuote = BigDecimal::of($pool->quote_reserve)
            ->multipliedBy('0.1'); // Use 10% of reserves for market making

        $orderSize = $availableQuote->dividedBy(self::DEPTH_LEVELS, 2, RoundingMode::DOWN);

        for ($i = 0; $i < self::DEPTH_LEVELS; $i++) {
            // Calculate price level
            $priceOffset = $spreadDecimal->plus($incrementDecimal->multipliedBy($i));
            $price = $midPrice->multipliedBy(BigDecimal::one()->minus($priceOffset));

            // Calculate order quantity
            $quantity = $orderSize->dividedBy($price, 8, RoundingMode::DOWN);

            $orders[] = [
                'type'     => 'buy',
                'price'    => $price->__toString(),
                'quantity' => $quantity->__toString(),
                'value'    => $orderSize->__toString(),
                'level'    => $i + 1,
                'source'   => 'amm',
                'pool_id'  => $pool->pool_id,
            ];
        }

        return $orders;
    }

    /**
     * Generate sell orders at different price levels.
     */
    private function generateSellOrders(
        LiquidityPool $pool,
        BigDecimal $midPrice,
        string $spread
    ): array {
        $orders = [];
        $spreadDecimal = BigDecimal::of($spread);
        $incrementDecimal = BigDecimal::of(self::DEPTH_INCREMENT);

        // Calculate available base currency for market making
        $availableBase = BigDecimal::of($pool->base_reserve)
            ->multipliedBy('0.1'); // Use 10% of reserves for market making

        $orderSize = $availableBase->dividedBy(self::DEPTH_LEVELS, 8, RoundingMode::DOWN);

        for ($i = 0; $i < self::DEPTH_LEVELS; $i++) {
            // Calculate price level
            $priceOffset = $spreadDecimal->plus($incrementDecimal->multipliedBy($i));
            $price = $midPrice->multipliedBy(BigDecimal::one()->plus($priceOffset));

            // Order quantity is fixed portion of base currency
            $quantity = $orderSize;
            $value = $quantity->multipliedBy($price);

            $orders[] = [
                'type'     => 'sell',
                'price'    => $price->__toString(),
                'quantity' => $quantity->__toString(),
                'value'    => $value->toScale(2, RoundingMode::DOWN)->__toString(),
                'level'    => $i + 1,
                'source'   => 'amm',
                'pool_id'  => $pool->pool_id,
            ];
        }

        return $orders;
    }

    /**
     * Calculate mid price from pool reserves.
     */
    private function calculateMidPrice(LiquidityPool $pool): BigDecimal
    {
        return BigDecimal::of($pool->quote_reserve)
            ->dividedBy($pool->base_reserve, 18, RoundingMode::HALF_UP);
    }

    /**
     * Calculate recent price volatility.
     */
    private function calculateRecentVolatility(LiquidityPool $pool): BigDecimal
    {
        // Get recent swaps from the last hour
        $recentSwaps = $pool->swaps()
            ->where('created_at', '>=', now()->subHour())
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        if ($recentSwaps->count() < 2) {
            return BigDecimal::zero();
        }

        // Calculate price changes
        $priceChanges = [];
        for ($i = 1; $i < $recentSwaps->count(); $i++) {
            $prevPrice = BigDecimal::of($recentSwaps[$i - 1]->execution_price);
            $currPrice = BigDecimal::of($recentSwaps[$i]->execution_price);

            if (! $prevPrice->isZero()) {
                $change = $currPrice->minus($prevPrice)
                    ->dividedBy($prevPrice, 18, RoundingMode::HALF_UP)
                    ->abs();
                $priceChanges[] = $change;
            }
        }

        if (empty($priceChanges)) {
            return BigDecimal::zero();
        }

        // Calculate standard deviation as volatility measure
        $mean = BigDecimal::sum(...$priceChanges)
            ->dividedBy(count($priceChanges), 18, RoundingMode::HALF_UP);

        $variance = BigDecimal::zero();
        foreach ($priceChanges as $change) {
            $diff = $change->minus($mean);
            $variance = $variance->plus($diff->multipliedBy($diff));
        }

        $variance = $variance->dividedBy(count($priceChanges), 18, RoundingMode::HALF_UP);

        // Return standard deviation
        return $variance->sqrt(18);
    }

    /**
     * Calculate order book depth.
     */
    private function calculateOrderBookDepth(?OrderBook $orderBook): float
    {
        if (! $orderBook) {
            return 0;
        }

        $bids = json_decode($orderBook->bids, true) ?? [];
        $asks = json_decode($orderBook->asks, true) ?? [];

        $bidDepth = array_sum(array_column($bids, 'quantity'));
        $askDepth = array_sum(array_column($asks, 'quantity'));

        return min($bidDepth, $askDepth);
    }

    /**
     * Calculate liquidity depth ratio.
     */
    private function calculateLiquidityDepth(LiquidityPool $pool): float
    {
        $tvl = BigDecimal::of($pool->base_reserve)
            ->multipliedBy(2); // Simplified TVL calculation

        $volume24h = BigDecimal::of($pool->volume_24h);

        if ($tvl->isZero()) {
            return 0;
        }

        return (float) $tvl->dividedBy($volume24h->isZero() ? BigDecimal::one() : $volume24h, 2, RoundingMode::DOWN)
            ->__toString();
    }

    /**
     * Adjust market making parameters based on performance.
     */
    public function adjustMarketMakingParameters(string $poolId): array
    {
        $pool = LiquidityPool::where('pool_id', $poolId)->firstOrFail();

        // Analyze recent market making performance
        $performance = $this->analyzeMarketMakingPerformance($pool);

        $adjustments = [];

        // Adjust spread if capture rate is low
        if ($performance['capture_rate'] < 0.5) {
            $adjustments['spread_adjustment'] = 'decrease';
            $adjustments['spread_factor'] = 0.9;
        } elseif ($performance['capture_rate'] > 0.8) {
            $adjustments['spread_adjustment'] = 'increase';
            $adjustments['spread_factor'] = 1.1;
        }

        // Adjust depth if inventory is imbalanced
        if ($performance['inventory_imbalance'] > 0.1) {
            $adjustments['depth_adjustment'] = 'skew';
            $adjustments['depth_skew_direction'] = $performance['inventory_direction'];
        }

        return $adjustments;
    }

    /**
     * Analyze market making performance.
     */
    private function analyzeMarketMakingPerformance(LiquidityPool $pool): array
    {
        // Get recent AMM orders
        $recentOrders = DB::table('orders')
            ->where('pool_id', $pool->pool_id)
            ->where('source', 'amm')
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        $totalOrders = $recentOrders->count();
        $filledOrders = $recentOrders->where('status', 'filled')->count();

        $captureRate = $totalOrders > 0 ? $filledOrders / $totalOrders : 0;

        // Calculate inventory imbalance
        $baseValue = BigDecimal::of($pool->base_reserve);
        $quoteValue = BigDecimal::of($pool->quote_reserve);
        $midPrice = $this->calculateMidPrice($pool);

        $baseValueInQuote = $baseValue->multipliedBy($midPrice);
        $totalValue = $baseValueInQuote->plus($quoteValue);

        $baseRatio = $totalValue->isZero()
            ? BigDecimal::zero()
            : $baseValueInQuote->dividedBy($totalValue, 18, RoundingMode::HALF_UP);

        $imbalance = $baseRatio->minus('0.5')->abs();
        $direction = $baseRatio->isGreaterThan('0.5') ? 'base_heavy' : 'quote_heavy';

        return [
            'capture_rate'        => $captureRate,
            'filled_orders'       => $filledOrders,
            'total_orders'        => $totalOrders,
            'inventory_imbalance' => (float) $imbalance->__toString(),
            'inventory_direction' => $direction,
        ];
    }
}
