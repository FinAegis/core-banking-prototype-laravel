<?php

namespace App\Domain\Exchange\Contracts;

use DateTimeInterface;

interface ExternalLiquidityServiceInterface
{
    /**
     * Find arbitrage opportunities between internal and external exchanges.
     */
    public function findArbitrageOpportunities(string $baseCurrency, string $quoteCurrency): array;

    /**
     * Provide liquidity from external sources when needed.
     */
    public function provideLiquidity(
        string $baseCurrency,
        string $quoteCurrency,
        string $side,
        string $amount
    ): array;

    /**
     * Align internal prices with external market prices.
     */
    public function alignPrices(
        string $baseCurrency,
        string $quoteCurrency,
        float $maxDeviationPercentage = 1.0
    ): array;

    /**
     * Execute arbitrage trade.
     */
    public function executeArbitrage(array $opportunity): array;

    /**
     * Get liquidity depth from external sources.
     */
    public function getExternalLiquidityDepth(string $baseCurrency, string $quoteCurrency): array;

    /**
     * Monitor price divergence.
     */
    public function monitorPriceDivergence(): array;

    /**
     * Rebalance liquidity across exchanges.
     */
    public function rebalanceLiquidity(array $targetDistribution): array;

    /**
     * Get arbitrage statistics.
     */
    public function getArbitrageStats(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): array;
}
