<?php

namespace App\Domain\Exchange\Contracts;

use App\Domain\Exchange\Projections\LiquidityPool as PoolProjection;
use App\Domain\Exchange\ValueObjects\LiquidityAdditionInput;
use App\Domain\Exchange\ValueObjects\LiquidityRemovalInput;
use Illuminate\Support\Collection;

interface LiquidityPoolServiceInterface
{
    /**
     * Create a new liquidity pool
     *
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @param string $feeRate
     * @param array $metadata
     * @return string
     */
    public function createPool(
        string $baseCurrency,
        string $quoteCurrency,
        string $feeRate = '0.003',
        array $metadata = []
    ): string;

    /**
     * Add liquidity to a pool
     *
     * @param LiquidityAdditionInput $input
     * @return array
     */
    public function addLiquidity(LiquidityAdditionInput $input): array;

    /**
     * Remove liquidity from a pool
     *
     * @param LiquidityRemovalInput $input
     * @return array
     */
    public function removeLiquidity(LiquidityRemovalInput $input): array;

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
     * Get pool details
     *
     * @param string $poolId
     * @return PoolProjection|null
     */
    public function getPool(string $poolId): ?PoolProjection;

    /**
     * Get pool by currency pair
     *
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @return PoolProjection|null
     */
    public function getPoolByPair(string $baseCurrency, string $quoteCurrency): ?PoolProjection;

    /**
     * Get all active pools
     *
     * @return Collection
     */
    public function getActivePools(): Collection;

    /**
     * Get provider's positions
     *
     * @param string $providerId
     * @return Collection
     */
    public function getProviderPositions(string $providerId): Collection;

    /**
     * Get pool metrics and analytics
     *
     * @param string $poolId
     * @return array
     */
    public function getPoolMetrics(string $poolId): array;
}