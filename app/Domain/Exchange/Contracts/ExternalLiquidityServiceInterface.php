<?php

namespace App\Domain\Exchange\Contracts;

interface ExternalLiquidityServiceInterface
{
    /**
     * Find arbitrage opportunities between internal and external exchanges
     *
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @return array
     */
    public function findArbitrageOpportunities(string $baseCurrency, string $quoteCurrency): array;

    /**
     * Provide liquidity from external sources when needed
     *
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @param string $side
     * @param string $amount
     * @return array
     */
    public function provideLiquidity(
        string $baseCurrency,
        string $quoteCurrency,
        string $side,
        string $amount
    ): array;

    /**
     * Align internal prices with external market prices
     *
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @param float $maxDeviationPercentage
     * @return array
     */
    public function alignPrices(
        string $baseCurrency,
        string $quoteCurrency,
        float $maxDeviationPercentage = 1.0
    ): array;

    /**
     * Execute arbitrage trade
     *
     * @param array $opportunity
     * @return array
     */
    public function executeArbitrage(array $opportunity): array;

    /**
     * Get liquidity depth from external sources
     *
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @return array
     */
    public function getExternalLiquidityDepth(string $baseCurrency, string $quoteCurrency): array;

    /**
     * Monitor price divergence
     *
     * @return array
     */
    public function monitorPriceDivergence(): array;

    /**
     * Rebalance liquidity across exchanges
     *
     * @param array $targetDistribution
     * @return array
     */
    public function rebalanceLiquidity(array $targetDistribution): array;

    /**
     * Get arbitrage statistics
     *
     * @param \DateTimeInterface|null $from
     * @param \DateTimeInterface|null $to
     * @return array
     */
    public function getArbitrageStats(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array;
}