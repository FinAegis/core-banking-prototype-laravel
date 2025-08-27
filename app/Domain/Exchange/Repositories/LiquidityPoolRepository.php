<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Repositories;

use App\Domain\Exchange\Aggregates\LiquidityPool;
use App\Domain\Exchange\Contracts\LiquidityPoolRepositoryInterface;
use Exception;
use Illuminate\Support\Collection;

/**
 * Event-sourced repository implementation for LiquidityPool aggregates.
 */
class LiquidityPoolRepository implements LiquidityPoolRepositoryInterface
{
    /**
     * Find a liquidity pool by ID.
     */
    public function find(string $poolId): ?LiquidityPool
    {
        try {
            return LiquidityPool::retrieve($poolId);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Find a pool by currency pair.
     */
    public function findByCurrencyPair(string $baseCurrency, string $quoteCurrency): ?LiquidityPool
    {
        $projection = \App\Domain\Exchange\Projections\LiquidityPool::where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency)
            ->first();

        return $projection ? $this->find($projection->pool_id) : null;
    }

    /**
     * Find all active liquidity pools.
     */
    public function findActive(): Collection
    {
        return \App\Domain\Exchange\Projections\LiquidityPool::where('status', 'active')
            ->get()
            ->map(fn ($projection) => $this->find($projection->pool_id))
            ->filter();
    }

    /**
     * Find pools by provider.
     */
    public function findByProvider(string $providerId): Collection
    {
        return \App\Domain\Exchange\Projections\LiquidityProvider::where('provider_id', $providerId)
            ->get()
            ->map(fn ($provider) => $this->find($provider->pool_id))
            ->filter()
            ->unique('pool_id');
    }

    /**
     * Save a liquidity pool aggregate.
     */
    public function save(LiquidityPool $pool): void
    {
        $pool->persist();
    }

    /**
     * Delete a liquidity pool.
     */
    public function delete(string $poolId): void
    {
        $pool = $this->find($poolId);
        if ($pool) {
            // Update pool status to deleted
            // Note: This would typically trigger an event to mark the pool as deleted
            // For now, we just persist the current state
            $pool->persist();
        }
    }

    /**
     * Get pool statistics.
     */
    public function getStatistics(string $poolId): array
    {
        $pool = \App\Domain\Exchange\Projections\LiquidityPool::find($poolId);

        if (! $pool) {
            return [];
        }

        return [
            'pool_id'              => $pool->pool_id,
            'base_currency'        => $pool->base_currency,
            'quote_currency'       => $pool->quote_currency,
            'base_liquidity'       => $pool->base_liquidity,
            'quote_liquidity'      => $pool->quote_liquidity,
            'total_value_locked'   => $pool->total_value_locked,
            'fee_percentage'       => $pool->fee_percentage,
            'total_fees_collected' => $pool->total_fees_collected,
            'total_volume'         => $pool->total_volume,
            'provider_count'       => $pool->providers()->count(),
            'status'               => $pool->status,
            'created_at'           => $pool->created_at,
            'updated_at'           => $pool->updated_at,
        ];
    }

    /**
     * Find pools with low liquidity.
     */
    public function findLowLiquidityPools(float $threshold): Collection
    {
        return \App\Domain\Exchange\Projections\LiquidityPool::where('total_value_locked', '<', $threshold)
            ->where('status', 'active')
            ->get()
            ->map(fn ($projection) => $this->find($projection->pool_id))
            ->filter();
    }

    /**
     * Find pools requiring rebalancing.
     */
    public function findPoolsNeedingRebalance(): Collection
    {
        // Find pools where the ratio between base and quote liquidity is off by more than 10%
        return \App\Domain\Exchange\Projections\LiquidityPool::whereRaw('
            ABS((base_liquidity * latest_price) - quote_liquidity) / quote_liquidity > 0.1
        ')
            ->where('status', 'active')
            ->get()
            ->map(fn ($projection) => $this->find($projection->pool_id))
            ->filter();
    }

    /**
     * Calculate total value locked (TVL) across all pools.
     */
    public function getTotalValueLocked(): float
    {
        return (float) \App\Domain\Exchange\Projections\LiquidityPool::where('status', 'active')
            ->sum('total_value_locked');
    }
}
