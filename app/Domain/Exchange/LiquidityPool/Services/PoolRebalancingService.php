<?php

namespace App\Domain\Exchange\LiquidityPool\Services;

use App\Domain\Exchange\Aggregates\LiquidityPool;
use App\Domain\Exchange\Contracts\PriceAggregatorInterface;
use App\Domain\Exchange\Projections\LiquidityPool as PoolProjection;
use App\Domain\Exchange\Services\ArbitrageService;
use App\Domain\Exchange\Workflows\LiquidityManagementWorkflow;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DB;
use Exception;
use Illuminate\Support\Collection;
use Workflow\WorkflowStub;

class PoolRebalancingService
{
    // Rebalancing thresholds
    private const PRICE_DEVIATION_THRESHOLD = '0.02'; // 2% deviation triggers rebalancing

    private const INVENTORY_IMBALANCE_THRESHOLD = '0.1'; // 10% imbalance

    private const MIN_REBALANCE_INTERVAL_HOURS = 1; // Don't rebalance more than once per hour

    // Rebalancing strategies
    private const STRATEGY_AGGRESSIVE = 'aggressive'; // Quick rebalancing, higher slippage tolerance

    private const STRATEGY_CONSERVATIVE = 'conservative'; // Slow rebalancing, lower slippage

    private const STRATEGY_ADAPTIVE = 'adaptive'; // Adjusts based on market conditions

    public function __construct(
        private readonly PriceAggregatorInterface $priceAggregator,
        private readonly ArbitrageService $arbitrageService
    ) {
    }

    /**
     * Check all pools and rebalance if needed.
     */
    public function rebalanceAllPools(): array
    {
        $results = [];
        $pools = PoolProjection::active()->get();

        foreach ($pools as $pool) {
            $result = $this->checkAndRebalancePool($pool);
            if ($result['needs_rebalancing']) {
                $results[$pool->pool_id] = $result;
            }
        }

        return $results;
    }

    /**
     * Check if a pool needs rebalancing and execute if necessary.
     */
    public function checkAndRebalancePool(PoolProjection $pool): array
    {
        // Check if enough time has passed since last rebalancing
        if (! $this->canRebalance($pool)) {
            return [
                'pool_id'           => $pool->pool_id,
                'needs_rebalancing' => false,
                'reason'            => 'Too soon since last rebalancing',
            ];
        }

        // Analyze pool state
        $analysis = $this->analyzePoolState($pool);

        if (! $analysis['needs_rebalancing']) {
            return [
                'pool_id'           => $pool->pool_id,
                'needs_rebalancing' => false,
                'metrics'           => $analysis,
            ];
        }

        // Determine rebalancing strategy
        $strategy = $this->determineRebalancingStrategy($analysis);

        // Execute rebalancing
        $result = $this->executeRebalancing($pool, $analysis, $strategy);

        return [
            'pool_id'           => $pool->pool_id,
            'needs_rebalancing' => true,
            'analysis'          => $analysis,
            'strategy'          => $strategy,
            'result'            => $result,
        ];
    }

    /**
     * Analyze pool state to determine if rebalancing is needed.
     */
    private function analyzePoolState(PoolProjection $pool): array
    {
        // Get external price
        $externalPrice = $this->priceAggregator->getAggregatedPrice(
            $pool->base_currency,
            $pool->quote_currency
        );

        // Calculate pool price
        $baseReserve = BigDecimal::of($pool->base_reserve);
        $quoteReserve = BigDecimal::of($pool->quote_reserve);
        $poolPrice = $quoteReserve->dividedBy($baseReserve, 18, RoundingMode::HALF_UP);

        // Calculate price deviation
        $priceDeviation = $poolPrice->minus($externalPrice)
            ->dividedBy($externalPrice, 18, RoundingMode::HALF_UP)
            ->abs();

        // Calculate inventory imbalance
        $targetRatio = $this->calculateTargetInventoryRatio($pool, $externalPrice);
        $currentRatio = $this->calculateCurrentInventoryRatio($pool);
        $inventoryImbalance = $currentRatio->minus($targetRatio)->abs();

        // Check arbitrage opportunities
        $arbitrageOpportunities = $this->checkArbitrageOpportunities($pool);

        // Determine if rebalancing is needed
        $needsRebalancing = $priceDeviation->isGreaterThan(self::PRICE_DEVIATION_THRESHOLD) ||
                           $inventoryImbalance->isGreaterThan(self::INVENTORY_IMBALANCE_THRESHOLD) ||
                           ! empty($arbitrageOpportunities);

        return [
            'needs_rebalancing'       => $needsRebalancing,
            'pool_price'              => $poolPrice->__toString(),
            'external_price'          => $externalPrice->__toString(),
            'price_deviation'         => $priceDeviation->__toString(),
            'current_inventory_ratio' => $currentRatio->__toString(),
            'target_inventory_ratio'  => $targetRatio->__toString(),
            'inventory_imbalance'     => $inventoryImbalance->__toString(),
            'arbitrage_opportunities' => $arbitrageOpportunities,
            'timestamp'               => now()->toIso8601String(),
        ];
    }

    /**
     * Calculate target inventory ratio based on market conditions.
     */
    private function calculateTargetInventoryRatio(PoolProjection $pool, BigDecimal $marketPrice): BigDecimal
    {
        // For a balanced AMM, target is typically 50/50
        // But we can adjust based on market trends

        // Get price trend
        $priceTrend = $this->calculatePriceTrend($pool);

        // Base target is 0.5 (50% base, 50% quote)
        $baseTarget = BigDecimal::of('0.5');

        // Adjust based on trend
        if ($priceTrend->isGreaterThan('0.01')) {
            // Price trending up, hold more base currency
            $adjustment = $priceTrend->multipliedBy('0.1')->min('0.05');

            return $baseTarget->plus($adjustment);
        } elseif ($priceTrend->isLessThan('-0.01')) {
            // Price trending down, hold less base currency
            $adjustment = $priceTrend->abs()->multipliedBy('0.1')->min('0.05');

            return $baseTarget->minus($adjustment);
        }

        return $baseTarget;
    }

    /**
     * Calculate current inventory ratio (base value / total value).
     */
    private function calculateCurrentInventoryRatio(PoolProjection $pool): BigDecimal
    {
        $baseReserve = BigDecimal::of($pool->base_reserve);
        $quoteReserve = BigDecimal::of($pool->quote_reserve);

        $poolPrice = $quoteReserve->dividedBy($baseReserve, 18, RoundingMode::HALF_UP);
        $baseValueInQuote = $baseReserve->multipliedBy($poolPrice);
        $totalValue = $baseValueInQuote->plus($quoteReserve);

        return $totalValue->isZero()
            ? BigDecimal::of('0.5')
            : $baseValueInQuote->dividedBy($totalValue, 18, RoundingMode::HALF_UP);
    }

    /**
     * Calculate price trend over recent period.
     */
    private function calculatePriceTrend(PoolProjection $pool): BigDecimal
    {
        // Get price 1 hour ago
        $priceHistory = DB::table('pool_swaps')
            ->where('pool_id', $pool->pool_id)
            ->where('created_at', '>=', now()->subHours(2))
            ->where('created_at', '<=', now()->subHour())
            ->avg('execution_price');

        if (! $priceHistory) {
            return BigDecimal::zero();
        }

        $oldPrice = BigDecimal::of($priceHistory);
        $currentPrice = BigDecimal::of($pool->quote_reserve)
            ->dividedBy($pool->base_reserve, 18, RoundingMode::HALF_UP);

        // Calculate percentage change
        return $currentPrice->minus($oldPrice)
            ->dividedBy($oldPrice, 18, RoundingMode::HALF_UP);
    }

    /**
     * Check for arbitrage opportunities.
     */
    private function checkArbitrageOpportunities(PoolProjection $pool): array
    {
        return $this->arbitrageService->findOpportunities(
            $pool->base_currency,
            $pool->quote_currency,
            BigDecimal::of($pool->base_reserve)->multipliedBy('0.01') // 1% of reserves
        );
    }

    /**
     * Determine rebalancing strategy based on market conditions.
     */
    private function determineRebalancingStrategy(array $analysis): array
    {
        $priceDeviation = BigDecimal::of($analysis['price_deviation']);
        $inventoryImbalance = BigDecimal::of($analysis['inventory_imbalance']);

        // High deviation requires aggressive rebalancing
        if ($priceDeviation->isGreaterThan('0.05') || $inventoryImbalance->isGreaterThan('0.2')) {
            return [
                'type'            => self::STRATEGY_AGGRESSIVE,
                'max_slippage'    => '0.02', // 2% slippage tolerance
                'execution_speed' => 'fast',
                'chunk_size'      => '0.1', // 10% of imbalance per chunk
            ];
        }

        // Moderate deviation uses adaptive strategy
        if ($priceDeviation->isGreaterThan('0.02') || $inventoryImbalance->isGreaterThan('0.1')) {
            return [
                'type'            => self::STRATEGY_ADAPTIVE,
                'max_slippage'    => '0.01', // 1% slippage tolerance
                'execution_speed' => 'moderate',
                'chunk_size'      => '0.05', // 5% of imbalance per chunk
            ];
        }

        // Low deviation uses conservative strategy
        return [
            'type'            => self::STRATEGY_CONSERVATIVE,
            'max_slippage'    => '0.005', // 0.5% slippage tolerance
            'execution_speed' => 'slow',
            'chunk_size'      => '0.02', // 2% of imbalance per chunk
        ];
    }

    /**
     * Execute rebalancing based on strategy.
     */
    private function executeRebalancing(
        PoolProjection $pool,
        array $analysis,
        array $strategy
    ): array {
        $currentRatio = BigDecimal::of($analysis['current_inventory_ratio']);
        $targetRatio = BigDecimal::of($analysis['target_inventory_ratio']);

        // Calculate rebalancing amounts
        $rebalanceAmounts = $this->calculateRebalancingAmounts($pool, $currentRatio, $targetRatio, $strategy);

        if ($rebalanceAmounts['amount']->isZero()) {
            return [
                'status' => 'skipped',
                'reason' => 'Rebalancing amount too small',
            ];
        }

        // Execute rebalancing through workflow
        $workflow = WorkflowStub::make(LiquidityManagementWorkflow::class);

        try {
            $result = $workflow->rebalancePool(
                poolId: $pool->pool_id,
                targetRatio: $targetRatio->__toString(),
                maxSlippage: $strategy['max_slippage'],
                metadata: [
                    'strategy' => $strategy,
                    'analysis' => $analysis,
                ]
            );

            // Update pool aggregate
            LiquidityPool::retrieve($pool->pool_id)
                ->rebalancePool(
                    targetRatio: $targetRatio->__toString(),
                    maxSlippage: $strategy['max_slippage'],
                    metadata: [
                        'executed_amount'   => $rebalanceAmounts['amount']->__toString(),
                        'executed_currency' => $rebalanceAmounts['currency'],
                        'strategy_type'     => $strategy['type'],
                    ]
                )
                ->persist();

            return [
                'status'            => 'success',
                'executed_amount'   => $rebalanceAmounts['amount']->__toString(),
                'executed_currency' => $rebalanceAmounts['currency'],
                'new_ratio'         => $this->calculateCurrentInventoryRatio($pool->fresh())->__toString(),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate amounts needed for rebalancing.
     */
    private function calculateRebalancingAmounts(
        PoolProjection $pool,
        BigDecimal $currentRatio,
        BigDecimal $targetRatio,
        array $strategy
    ): array {
        $baseReserve = BigDecimal::of($pool->base_reserve);
        $quoteReserve = BigDecimal::of($pool->quote_reserve);
        $poolPrice = $quoteReserve->dividedBy($baseReserve, 18, RoundingMode::HALF_UP);

        // Calculate total value in quote currency
        $baseValueInQuote = $baseReserve->multipliedBy($poolPrice);
        $totalValue = $baseValueInQuote->plus($quoteReserve);

        // Calculate target reserves
        $targetBaseValue = $totalValue->multipliedBy($targetRatio);
        $targetQuoteValue = $totalValue->minus($targetBaseValue);
        $targetBaseReserve = $targetBaseValue->dividedBy($poolPrice, 18, RoundingMode::HALF_UP);
        $targetQuoteReserve = $targetQuoteValue;

        // Calculate differences
        $baseDiff = $targetBaseReserve->minus($baseReserve);
        $quoteDiff = $targetQuoteReserve->minus($quoteReserve);

        // Apply chunk size from strategy
        $chunkSize = BigDecimal::of($strategy['chunk_size']);

        if ($baseDiff->isPositive()) {
            // Need to buy base currency
            return [
                'action'       => 'buy_base',
                'currency'     => $pool->base_currency,
                'amount'       => $baseDiff->multipliedBy($chunkSize),
                'quote_needed' => $baseDiff->multipliedBy($chunkSize)->multipliedBy($poolPrice),
            ];
        } else {
            // Need to sell base currency
            return [
                'action'         => 'sell_base',
                'currency'       => $pool->base_currency,
                'amount'         => $baseDiff->abs()->multipliedBy($chunkSize),
                'quote_received' => $baseDiff->abs()->multipliedBy($chunkSize)->multipliedBy($poolPrice),
            ];
        }
    }

    /**
     * Check if enough time has passed since last rebalancing.
     */
    private function canRebalance(PoolProjection $pool): bool
    {
        // Get last rebalancing event
        $lastRebalancing = DB::table('exchange_events')
            ->where('aggregate_uuid', $pool->pool_id)
            ->where('event_class', 'LIKE', '%LiquidityPoolRebalanced%')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $lastRebalancing) {
            return true;
        }

        $hoursSinceLastRebalancing = now()->diffInHours($lastRebalancing->created_at);

        return $hoursSinceLastRebalancing >= self::MIN_REBALANCE_INTERVAL_HOURS;
    }

    /**
     * Get rebalancing history for a pool.
     */
    public function getRebalancingHistory(string $poolId, int $days = 30): Collection
    {
        return DB::table('exchange_events')
            ->where('aggregate_uuid', $poolId)
            ->where('event_class', 'LIKE', '%LiquidityPoolRebalanced%')
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(
                function ($event) {
                    $eventData = json_decode($event->event_properties, true);

                    return [
                    'timestamp'          => $event->created_at,
                    'old_ratio'          => $eventData['old_ratio'] ?? null,
                    'new_ratio'          => $eventData['new_ratio'] ?? null,
                    'rebalance_amount'   => $eventData['rebalance_amount'] ?? null,
                    'rebalance_currency' => $eventData['rebalance_currency'] ?? null,
                    ];
                }
            );
    }
}
