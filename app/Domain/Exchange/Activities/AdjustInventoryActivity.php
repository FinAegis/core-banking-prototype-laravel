<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Activities;

use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Domain\Exchange\Services\OrderService;
use Workflow\Activity\ActivityInterface;
use Workflow\Activity\ActivityMethod;

#[ActivityInterface]
class AdjustInventoryActivity
{
    public function __construct(
        private readonly LiquidityPoolService $poolService,
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * Adjust inventory to maintain target ratio.
     *
     * @param string $poolId Pool ID
     * @param string $baseCurrency Base currency
     * @param string $quoteCurrency Quote currency
     * @param float $targetRatio Target ratio of base value to total value
     * @return array Result of inventory adjustment
     */
    #[ActivityMethod]
    public function execute(
        string $poolId,
        string $baseCurrency,
        string $quoteCurrency,
        float $targetRatio
    ): array {
        $pool = $this->poolService->getPool($poolId);

        // Calculate current ratio
        $midPrice = (float) $pool->quote_reserve / (float) $pool->base_reserve;
        $baseValue = (float) $pool->base_reserve * $midPrice;
        $totalValue = $baseValue + (float) $pool->quote_reserve;
        $currentRatio = $baseValue / $totalValue;

        // Determine adjustment needed
        $ratioDiff = $targetRatio - $currentRatio;

        if (abs($ratioDiff) < 0.05) {
            // Close enough to target
            return [
                'status'        => 'balanced',
                'current_ratio' => $currentRatio,
                'target_ratio'  => $targetRatio,
            ];
        }

        // Calculate trade size needed
        $adjustmentValue = abs($ratioDiff) * $totalValue * 0.5; // Adjust 50% of the difference

        if ($ratioDiff > 0) {
            // Need more base currency - buy base
            $orderAmount = $adjustmentValue / $midPrice;

            // Place market buy order
            $this->orderService->placeOrder(
                accountId: 'market-maker-rebalance',
                type: 'BUY',
                baseCurrency: $baseCurrency,
                quoteCurrency: $quoteCurrency,
                price: (string) $midPrice,
                quantity: (string) $orderAmount,
                orderType: 'MARKET'
            );

            return [
                'status'        => 'rebalanced',
                'action'        => 'bought_base',
                'amount'        => $orderAmount,
                'current_ratio' => $currentRatio,
                'target_ratio'  => $targetRatio,
            ];
        } else {
            // Need more quote currency - sell base
            $orderAmount = $adjustmentValue / $midPrice;

            // Place market sell order
            $this->orderService->placeOrder(
                accountId: 'market-maker-rebalance',
                type: 'SELL',
                baseCurrency: $baseCurrency,
                quoteCurrency: $quoteCurrency,
                price: (string) $midPrice,
                quantity: (string) $orderAmount,
                orderType: 'MARKET'
            );

            return [
                'status'        => 'rebalanced',
                'action'        => 'sold_base',
                'amount'        => $orderAmount,
                'current_ratio' => $currentRatio,
                'target_ratio'  => $targetRatio,
            ];
        }
    }
}
