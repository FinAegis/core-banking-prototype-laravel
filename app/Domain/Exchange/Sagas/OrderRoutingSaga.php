<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Sagas;

use App\Domain\Exchange\Events\OrderPlaced;
use App\Domain\Exchange\Events\OrderRouted;
use App\Domain\Exchange\Events\OrderSplit;
use App\Domain\Exchange\Events\RoutingFailed;
use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Domain\Exchange\Services\OrderService;
use Illuminate\Support\Facades\Log;
use Spatie\EventSourcing\EventHandlers\Reactors\Reactor;

/**
 * Saga for intelligent order routing across multiple liquidity pools.
 * Finds optimal execution paths considering price impact, fees, and available liquidity.
 */
class OrderRoutingSaga extends Reactor
{
    private const MAX_PRICE_IMPACT = 0.05; // 5% maximum acceptable price impact

    private const MIN_SPLIT_SIZE = 100; // Minimum order size for splitting (in USD)

    private const MAX_ROUTES = 5; // Maximum number of routes to consider

    public function __construct(
        private readonly LiquidityPoolService $poolService,
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * Handle order placed event - find optimal routing.
     */
    public function onOrderPlaced(OrderPlaced $event): void
    {
        try {
            // Get all available pools for this trading pair
            $availablePools = $this->getAvailablePools(
                $event->baseCurrency,
                $event->quoteCurrency
            );

            if (empty($availablePools)) {
                $this->handleNoLiquidityAvailable($event);

                return;
            }

            // Calculate optimal routing strategy
            $routingStrategy = $this->calculateOptimalRouting(
                $event,
                $availablePools
            );

            // Execute routing based on strategy
            if ($routingStrategy['split_required']) {
                $this->executeSplitRouting($event, $routingStrategy);
            } else {
                $this->executeSingleRouting($event, $routingStrategy);
            }
        } catch (\Exception $e) {
            Log::error('Order routing failed', [
                'order_id' => $event->orderId,
                'error'    => $e->getMessage(),
            ]);

            event(new RoutingFailed(
                orderId: $event->orderId,
                reason: $e->getMessage(),
                timestamp: now()
            ));
        }
    }

    /**
     * Get available liquidity pools for the trading pair.
     */
    private function getAvailablePools(string $baseCurrency, string $quoteCurrency): array
    {
        $pools = $this->poolService->getPoolsForPair($baseCurrency, $quoteCurrency);

        // Filter pools by minimum liquidity and status
        return array_filter($pools, function ($pool) {
            $liquidity = $this->calculatePoolLiquidity($pool);

            return $pool->is_active && $liquidity > 1000; // Min $1000 liquidity
        });
    }

    /**
     * Calculate optimal routing strategy for the order.
     */
    private function calculateOptimalRouting(OrderPlaced $event, array $pools): array
    {
        $orderAmount = (float) $event->amount; // Order amount in base currency
        $orderSizeUSD = $orderAmount * $this->getAssetPrice($event->baseCurrency);
        $routes = [];

        foreach ($pools as $pool) {
            $liquidity = $this->calculatePoolLiquidity($pool);
            $priceImpact = $this->estimatePriceImpact($pool, $orderAmount);
            $feeTier = $this->getPoolFeeTier($pool);

            // Calculate effective price including fees and slippage
            $effectivePrice = $this->calculateEffectivePrice(
                $pool,
                $event->type, // Using 'type' instead of 'side' (buy/sell)
                $orderAmount,
                $priceImpact,
                $feeTier
            );

            $routes[] = [
                'pool_id'         => $pool->pool_id,
                'liquidity'       => $liquidity,
                'price_impact'    => $priceImpact,
                'fee_tier'        => $feeTier,
                'effective_price' => $effectivePrice,
                'max_size'        => $this->calculateMaxOrderSize($pool, $priceImpact),
            ];
        }

        // Sort routes by effective price (best price first)
        usort($routes, function ($a, $b) use ($event) {
            if ($event->type === 'buy') {
                return $a['effective_price'] <=> $b['effective_price']; // Lower is better for buys
            } else {
                return $b['effective_price'] <=> $a['effective_price']; // Higher is better for sells
            }
        });

        // Take top routes
        $topRoutes = array_slice($routes, 0, self::MAX_ROUTES);

        // Determine if order splitting is beneficial (pass amount in base currency, not USD)
        $splitRequired = $this->shouldSplitOrder($orderAmount, $orderSizeUSD, $topRoutes);

        if ($splitRequired) {
            return $this->planSplitRouting($event, $topRoutes);
        }

        return [
            'split_required' => false,
            'primary_route'  => $topRoutes[0],
            'routes'         => [$topRoutes[0]],
        ];
    }

    /**
     * Determine if order should be split across multiple pools.
     */
    private function shouldSplitOrder(float $orderAmount, float $orderSizeUSD, array $routes): bool
    {
        if (count($routes) < 2) {
            return false;
        }

        // Check if any single pool can handle the entire order without excessive impact
        foreach ($routes as $route) {
            // Compare order amount (in base currency) with max_size (also in base currency)
            if ($route['max_size'] >= $orderAmount && $route['price_impact'] < 0.02) {
                return false; // Single pool can handle it efficiently
            }
        }

        // Check if splitting would reduce overall costs
        $singlePoolCost = $orderSizeUSD * $routes[0]['effective_price'];
        $splitCost = $this->estimateSplitCost($orderSizeUSD, $routes);

        return $splitCost < $singlePoolCost * 0.99; // Split if saves >1%
    }

    /**
     * Plan order splitting across multiple pools.
     */
    private function planSplitRouting(OrderPlaced $event, array $routes): array
    {
        $remainingSize = (float) $event->amount;
        $allocations = [];

        foreach ($routes as $route) {
            if ($remainingSize <= 0) {
                break;
            }

            // Allocate portion based on liquidity and price impact
            $allocation = min(
                $remainingSize,
                $route['max_size'] * 0.8 // Use 80% of max to avoid excessive impact
            );

            if ($allocation * $this->getAssetPrice($event->baseCurrency) >= self::MIN_SPLIT_SIZE) {
                $allocations[] = [
                    'pool_id'         => $route['pool_id'],
                    'amount'          => $allocation,
                    'fee_tier'        => $route['fee_tier'],
                    'estimated_price' => $route['effective_price'],
                ];

                $remainingSize -= $allocation;
            }
        }

        return [
            'split_required'  => true,
            'primary_route'   => $routes[0],
            'routes'          => $allocations,
            'total_allocated' => (float) $event->amount - $remainingSize,
        ];
    }

    /**
     * Execute single pool routing.
     */
    private function executeSingleRouting(OrderPlaced $event, array $strategy): void
    {
        $route = $strategy['primary_route'];

        event(new OrderRouted(
            orderId: $event->orderId,
            poolId: $route['pool_id'],
            amount: (float) $event->amount,
            estimatedPrice: $route['effective_price'],
            feeTier: $route['fee_tier'],
            timestamp: now()
        ));

        // Update order with routing information
        $this->orderService->updateOrderRouting(
            $event->orderId,
            $route['pool_id'],
            $route['effective_price']
        );

        Log::info('Order routed to single pool', [
            'order_id' => $event->orderId,
            'pool_id'  => $route['pool_id'],
            'amount'   => (float) $event->amount,
        ]);
    }

    /**
     * Execute split routing across multiple pools.
     */
    private function executeSplitRouting(OrderPlaced $event, array $strategy): void
    {
        event(new OrderSplit(
            orderId: $event->orderId,
            splits: $strategy['routes'],
            totalAmount: $strategy['total_allocated'],
            timestamp: now()
        ));

        foreach ($strategy['routes'] as $index => $route) {
            $childOrderId = $event->orderId . '-' . ($index + 1);

            // Create child order for each split
            $this->orderService->createChildOrder(
                $childOrderId,
                $event->orderId,
                $route['pool_id'],
                $route['amount'],
                $route['estimated_price']
            );

            event(new OrderRouted(
                orderId: $childOrderId,
                poolId: $route['pool_id'],
                amount: $route['amount'],
                estimatedPrice: $route['estimated_price'],
                feeTier: $route['fee_tier'],
                timestamp: now()
            ));
        }

        Log::info('Order split across multiple pools', [
            'order_id'     => $event->orderId,
            'splits'       => count($strategy['routes']),
            'total_amount' => $strategy['total_allocated'],
        ]);
    }

    /**
     * Handle case when no liquidity is available.
     */
    private function handleNoLiquidityAvailable(OrderPlaced $event): void
    {
        event(new RoutingFailed(
            orderId: $event->orderId,
            reason: 'No liquidity available for trading pair',
            timestamp: now()
        ));

        $this->orderService->rejectOrder(
            $event->orderId,
            'No liquidity available'
        );
    }

    /**
     * Calculate pool's total liquidity in USD.
     */
    private function calculatePoolLiquidity($pool): float
    {
        $basePrice = $this->getAssetPrice($pool->base_currency);
        $quotePrice = $this->getAssetPrice($pool->quote_currency);

        return ((float) $pool->base_reserve * $basePrice) + ((float) $pool->quote_reserve * $quotePrice);
    }

    /**
     * Estimate price impact for given order size.
     */
    private function estimatePriceImpact($pool, float $orderSize): float
    {
        $poolLiquidity = $this->calculatePoolLiquidity($pool);

        if ($poolLiquidity == 0) {
            return 1.0; // 100% impact if no liquidity
        }

        // Simple impact model: impact increases with order size relative to pool
        $relativeSize = ($orderSize * $this->getAssetPrice($pool->base_currency)) / $poolLiquidity;

        // Quadratic impact model
        return min(1.0, $relativeSize * $relativeSize * 2);
    }

    /**
     * Get fee tier for the pool.
     */
    private function getPoolFeeTier($pool): float
    {
        // Get fee tier from pool metadata or use default
        return $pool->metadata['fee_tier'] ?? 0.003; // Default 0.3%
    }

    /**
     * Calculate effective price including all costs.
     */
    private function calculateEffectivePrice(
        $pool,
        string $side,
        float $amount,
        float $priceImpact,
        float $feeTier
    ): float {
        $basePrice = $this->getAssetPrice($pool->base_currency);

        if ($side === 'buy') {
            // For buys, price increases with impact
            return $basePrice * (1 + $priceImpact) * (1 + $feeTier);
        } else {
            // For sells, price decreases with impact
            return $basePrice * (1 - $priceImpact) * (1 - $feeTier);
        }
    }

    /**
     * Calculate maximum order size for acceptable impact.
     */
    private function calculateMaxOrderSize($pool, float $currentImpact): float
    {
        $poolLiquidity = $this->calculatePoolLiquidity($pool);
        $basePrice = $this->getAssetPrice($pool->base_currency);

        // Calculate size that would cause max acceptable impact
        $maxImpactSize = sqrt(self::MAX_PRICE_IMPACT / 2) * $poolLiquidity / $basePrice;

        return max(0, $maxImpactSize);
    }

    /**
     * Estimate total cost of split routing.
     */
    private function estimateSplitCost(float $orderSize, array $routes): float
    {
        $totalCost = 0;
        $remainingSize = $orderSize;

        foreach ($routes as $route) {
            if ($remainingSize <= 0) {
                break;
            }

            $allocation = min($remainingSize, $route['max_size'] * 0.8);
            $totalCost += $allocation * $route['effective_price'];
            $remainingSize -= $allocation;
        }

        return $totalCost;
    }

    /**
     * Get asset price in USD.
     */
    private function getAssetPrice(string $assetCode): float
    {
        // This would integrate with price oracle
        return match ($assetCode) {
            'BTC' => 50000,
            'ETH' => 3000,
            'USD', 'USDT', 'USDC' => 1,
            default => 100,
        };
    }
}
