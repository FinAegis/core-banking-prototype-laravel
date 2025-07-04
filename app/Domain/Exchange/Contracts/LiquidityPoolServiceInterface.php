<?php

namespace App\Domain\Exchange\Contracts;

interface LiquidityPoolServiceInterface
{
    /**
     * Create a new liquidity pool
     *
     * @param string $accountId
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @param string $baseAmount
     * @param string $quoteAmount
     * @param array $config
     * @return array
     */
    public function createPool(
        string $accountId,
        string $baseCurrency,
        string $quoteCurrency,
        string $baseAmount,
        string $quoteAmount,
        array $config = []
    ): array;

    /**
     * Add liquidity to a pool
     *
     * @param string $poolId
     * @param string $accountId
     * @param string $baseAmount
     * @param string $quoteAmount
     * @return array
     */
    public function addLiquidity(
        string $poolId,
        string $accountId,
        string $baseAmount,
        string $quoteAmount
    ): array;

    /**
     * Remove liquidity from a pool
     *
     * @param string $poolId
     * @param string $accountId
     * @param string $lpTokenAmount
     * @return array
     */
    public function removeLiquidity(
        string $poolId,
        string $accountId,
        string $lpTokenAmount
    ): array;

    /**
     * Execute a swap through a liquidity pool
     *
     * @param string $poolId
     * @param string $accountId
     * @param string $inputCurrency
     * @param string $inputAmount
     * @param string $minOutputAmount
     * @return array
     */
    public function swap(
        string $poolId,
        string $accountId,
        string $inputCurrency,
        string $inputAmount,
        string $minOutputAmount
    ): array;

    /**
     * Get pool information
     *
     * @param string $poolId
     * @return array
     */
    public function getPoolInfo(string $poolId): array;

    /**
     * Calculate output amount for a swap
     *
     * @param string $poolId
     * @param string $inputCurrency
     * @param string $inputAmount
     * @return array
     */
    public function calculateSwapOutput(
        string $poolId,
        string $inputCurrency,
        string $inputAmount
    ): array;

    /**
     * Get user's liquidity positions
     *
     * @param string $accountId
     * @return array
     */
    public function getUserPositions(string $accountId): array;

    /**
     * Get pool metrics and analytics
     *
     * @param string $poolId
     * @return array
     */
    public function getPoolMetrics(string $poolId): array;
}