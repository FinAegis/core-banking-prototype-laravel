<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Contracts;

use App\Domain\Exchange\Aggregates\LiquidityPool;
use Illuminate\Support\Collection;

/**
 * Repository interface for LiquidityPool aggregate persistence.
 */
interface LiquidityPoolRepositoryInterface
{
    /**
     * Find a liquidity pool by ID.
     */
    public function find(string $poolId): ?LiquidityPool;

    /**
     * Find a pool by currency pair.
     */
    public function findByCurrencyPair(string $baseCurrency, string $quoteCurrency): ?LiquidityPool;

    /**
     * Find all active liquidity pools.
     */
    public function findActive(): Collection;

    /**
     * Find pools by provider.
     */
    public function findByProvider(string $providerId): Collection;

    /**
     * Save a liquidity pool aggregate.
     */
    public function save(LiquidityPool $pool): void;

    /**
     * Delete a liquidity pool.
     */
    public function delete(string $poolId): void;

    /**
     * Get pool statistics.
     */
    public function getStatistics(string $poolId): array;

    /**
     * Find pools with low liquidity.
     */
    public function findLowLiquidityPools(float $threshold): Collection;

    /**
     * Find pools requiring rebalancing.
     */
    public function findPoolsNeedingRebalance(): Collection;

    /**
     * Calculate total value locked (TVL) across all pools.
     */
    public function getTotalValueLocked(): float;
}
