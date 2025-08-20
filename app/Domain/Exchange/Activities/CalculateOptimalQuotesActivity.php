<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Activities;

use App\Domain\Exchange\Services\LiquidityPoolService;
use Workflow\Activity\ActivityInterface;
use Workflow\Activity\ActivityMethod;

#[ActivityInterface]
class CalculateOptimalQuotesActivity
{
    public function __construct(
        private readonly LiquidityPoolService $poolService,
    ) {
    }

    /**
     * Calculate optimal bid and ask quotes based on market conditions.
     *
     * @param string $poolId Pool ID
     * @param array $marketConditions Current market conditions
     * @param int $spreadBps Target spread in basis points
     * @param float $orderSize Size of each order
     * @return array Calculated quotes with bids and asks
     */
    #[ActivityMethod]
    public function execute(
        string $poolId,
        array $marketConditions,
        int $spreadBps,
        float $orderSize
    ): array {
        $pool = $this->poolService->getPool($poolId);

        // Get current mid price
        $midPrice = $marketConditions['mid_price'] ?? $this->calculateMidPrice($pool);

        // Calculate spread
        $spreadMultiplier = $spreadBps / 10000; // Convert basis points to decimal
        $halfSpread = $midPrice * $spreadMultiplier / 2;

        // Adjust for inventory imbalance
        $inventoryAdjustment = $this->calculateInventoryAdjustment(
            $marketConditions['inventory'] ?? [],
            $pool
        );

        // Adjust for volatility
        $volatilityAdjustment = $this->calculateVolatilityAdjustment(
            $marketConditions['volatility'] ?? 0
        );

        // Calculate final bid/ask prices
        $bidPrice = $midPrice - $halfSpread - $inventoryAdjustment - $volatilityAdjustment;
        $askPrice = $midPrice + $halfSpread + $inventoryAdjustment + $volatilityAdjustment;

        // Generate multiple levels of quotes for depth
        $bids = [];
        $asks = [];

        for ($i = 0; $i < 3; $i++) {
            $levelAdjustment = $i * $halfSpread * 0.5; // Each level 50% wider

            $bids[] = [
                'price' => round($bidPrice - $levelAdjustment, 8),
                'size'  => $orderSize * (1 - $i * 0.2), // Reduce size for deeper levels
            ];

            $asks[] = [
                'price' => round($askPrice + $levelAdjustment, 8),
                'size'  => $orderSize * (1 - $i * 0.2),
            ];
        }

        return [
            'bids'      => $bids,
            'asks'      => $asks,
            'spread'    => round(($askPrice - $bidPrice) / $midPrice * 10000, 2), // Spread in bps
            'mid_price' => $midPrice,
        ];
    }

    /**
     * Calculate mid price from pool reserves.
     */
    private function calculateMidPrice($pool): float
    {
        if ((float) $pool->base_reserve == 0 || (float) $pool->quote_reserve == 0) {
            return 1.0;
        }

        return (float) $pool->quote_reserve / (float) $pool->base_reserve;
    }

    /**
     * Calculate price adjustment based on inventory imbalance.
     */
    private function calculateInventoryAdjustment(array $inventory, $pool): float
    {
        if (empty($inventory)) {
            return 0;
        }

        $baseInventory = $inventory[$pool->base_currency] ?? 0;
        $quoteInventory = $inventory[$pool->quote_currency] ?? 0;

        $midPrice = $this->calculateMidPrice($pool);
        $baseValue = $baseInventory * $midPrice;
        $totalValue = $baseValue + $quoteInventory;

        if ($totalValue == 0) {
            return 0;
        }

        // If we have too much base, lower bid and raise ask to sell base
        $baseRatio = $baseValue / $totalValue;
        $imbalance = $baseRatio - 0.5;

        // Adjust by up to 0.5% of mid price based on imbalance
        return $midPrice * $imbalance * 0.005;
    }

    /**
     * Calculate price adjustment based on volatility.
     */
    private function calculateVolatilityAdjustment(float $volatility): float
    {
        // Widen spread in high volatility (up to 2% additional)
        return min($volatility * 20, 0.02);
    }
}
