<?php

namespace App\Domain\Exchange\Contracts;

use App\Domain\Exchange\Projections\LiquidityPool as PoolProjection;
use Illuminate\Support\Collection;

interface LiquidityPoolServiceInterface
{
    /**
     * Create a new liquidity pool.
     */
    public function createPool(
        string $baseCurrency,
        string $quoteCurrency,
        string $feeRate = '0.003',
        array $metadata = []
    ): string;

    /**
     * Execute a swap through a liquidity pool.
     */
    public function swap(
        string $poolId,
        string $accountId,
        string $inputCurrency,
        string $inputAmount,
        string $minOutputAmount
    ): array;

    /**
     * Get pool details.
     */
    public function getPool(string $poolId): ?PoolProjection;

    /**
     * Get pool by currency pair.
     */
    public function getPoolByPair(string $baseCurrency, string $quoteCurrency): ?PoolProjection;

    /**
     * Get all active pools.
     */
    public function getActivePools(): Collection;

    /**
     * Get provider's positions.
     */
    public function getProviderPositions(string $providerId): Collection;

    /**
     * Get pool metrics and analytics.
     */
    public function getPoolMetrics(string $poolId): array;
}
