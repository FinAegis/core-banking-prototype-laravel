<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Contracts;

use App\Domain\Stablecoin\Aggregates\StablecoinAggregate;
use Illuminate\Support\Collection;

/**
 * Repository interface for StablecoinAggregate persistence.
 */
interface StablecoinAggregateRepositoryInterface
{
    /**
     * Find a stablecoin aggregate by ID.
     */
    public function find(string $stablecoinId): ?StablecoinAggregate;

    /**
     * Find a stablecoin by code.
     */
    public function findByCode(string $code): ?StablecoinAggregate;

    /**
     * Find all active stablecoins.
     */
    public function findActive(): Collection;

    /**
     * Save a stablecoin aggregate.
     */
    public function save(StablecoinAggregate $stablecoin): void;

    /**
     * Delete a stablecoin.
     */
    public function delete(string $stablecoinId): void;

    /**
     * Find stablecoins by peg asset.
     */
    public function findByPegAsset(string $pegAssetCode): Collection;

    /**
     * Get collateralization metrics for a stablecoin.
     */
    public function getCollateralizationMetrics(string $stablecoinId): array;

    /**
     * Find under-collateralized stablecoins.
     */
    public function findUnderCollateralized(float $threshold): Collection;

    /**
     * Get total supply for a stablecoin.
     */
    public function getTotalSupply(string $stablecoinCode): float;

    /**
     * Get reserve statistics.
     */
    public function getReserveStatistics(string $stablecoinId): array;
}
