<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Services;

use App\Domain\Treasury\Aggregates\PortfolioAggregate;
use App\Domain\Treasury\ValueObjects\InvestmentStrategy;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PortfolioManagementService
{
    private const CACHE_TTL = 300; // 5 minutes cache for portfolio queries

    public function createPortfolio(string $treasuryId, string $name, array $strategy): string
    {
        if (empty($treasuryId)) {
            throw new InvalidArgumentException('Treasury ID cannot be empty');
        }

        if (empty($name)) {
            throw new InvalidArgumentException('Portfolio name cannot be empty');
        }

        if (empty($strategy)) {
            throw new InvalidArgumentException('Investment strategy cannot be empty');
        }

        // Validate strategy structure
        $this->validateStrategyStructure($strategy);

        try {
            $portfolioId = Str::uuid()->toString();
            $investmentStrategy = InvestmentStrategy::fromArray($strategy);

            $aggregate = PortfolioAggregate::retrieve($portfolioId);
            $aggregate->createPortfolio(
                $portfolioId,
                $treasuryId,
                $name,
                $investmentStrategy,
                ['created_at' => now()->toISOString(), 'created_by' => 'system']
            );

            $aggregate->persist();

            // Clear portfolios cache for this treasury
            $this->clearPortfoliosCache($treasuryId);

            return $portfolioId;
        } catch (Exception $e) {
            throw new RuntimeException("Failed to create portfolio: {$e->getMessage()}", 0, $e);
        }
    }

    public function getPortfolio(string $portfolioId): array
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        $cacheKey = "portfolio:{$portfolioId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($portfolioId) {
            try {
                $aggregate = PortfolioAggregate::retrieve($portfolioId);

                return [
                    'portfolio_id'      => $aggregate->getPortfolioId(),
                    'treasury_id'       => $aggregate->getTreasuryId(),
                    'name'              => $aggregate->getName(),
                    'strategy'          => $aggregate->getStrategy()?->toArray() ?? [],
                    'asset_allocations' => array_map(
                        fn ($allocation) => is_array($allocation) ? $allocation : $allocation->toArray(),
                        $aggregate->getAssetAllocations()
                    ),
                    'latest_metrics'      => $aggregate->getLatestMetrics()?->toArray() ?? [],
                    'total_value'         => $aggregate->getTotalValue(),
                    'status'              => $aggregate->getStatus(),
                    'is_rebalancing'      => $aggregate->isRebalancing(),
                    'last_rebalance_date' => $aggregate->getLastRebalanceDate(),
                ];
            } catch (Exception $e) {
                throw new RuntimeException("Failed to retrieve portfolio {$portfolioId}: {$e->getMessage()}", 0, $e);
            }
        });
    }

    public function updateStrategy(string $portfolioId, array $strategy): void
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        if (empty($strategy)) {
            throw new InvalidArgumentException('Investment strategy cannot be empty');
        }

        // Validate strategy structure
        $this->validateStrategyStructure($strategy);

        try {
            $investmentStrategy = InvestmentStrategy::fromArray($strategy);

            $aggregate = PortfolioAggregate::retrieve($portfolioId);
            $aggregate->updateStrategy(
                $investmentStrategy,
                'manual_update',
                'system'
            );

            $aggregate->persist();

            // Clear portfolio cache
            Cache::forget("portfolio:{$portfolioId}");
        } catch (Exception $e) {
            throw new RuntimeException("Failed to update portfolio strategy: {$e->getMessage()}", 0, $e);
        }
    }

    public function allocateAssets(string $portfolioId, array $allocations): void
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        if (empty($allocations)) {
            throw new InvalidArgumentException('Asset allocations cannot be empty');
        }

        // Validate allocations structure
        $this->validateAllocationsStructure($allocations);

        try {
            $allocationId = Str::uuid()->toString();
            $totalAmount = array_sum(array_column($allocations, 'amount'));

            $aggregate = PortfolioAggregate::retrieve($portfolioId);
            $aggregate->allocateAssets(
                $allocationId,
                $allocations,
                $totalAmount,
                'system'
            );

            $aggregate->persist();

            // Clear portfolio cache
            Cache::forget("portfolio:{$portfolioId}");
        } catch (Exception $e) {
            throw new RuntimeException("Failed to allocate assets: {$e->getMessage()}", 0, $e);
        }
    }

    public function listPortfolios(string $treasuryId): Collection
    {
        if (empty($treasuryId)) {
            throw new InvalidArgumentException('Treasury ID cannot be empty');
        }

        $cacheKey = "portfolios:{$treasuryId}";

        $portfolios = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            // In a real implementation, this would query the event store or projections
            // For now, we'll return a basic structure
            return [];
        });

        return collect($portfolios);
    }

    /**
     * Get portfolio summary statistics.
     */
    public function getPortfolioSummary(string $portfolioId): array
    {
        $portfolio = $this->getPortfolio($portfolioId);

        $totalAllocations = count($portfolio['asset_allocations']);
        $totalDrift = 0.0;
        $maxDrift = 0.0;

        foreach ($portfolio['asset_allocations'] as $allocation) {
            $drift = $allocation['drift'] ?? 0.0;
            $totalDrift += $drift;
            $maxDrift = max($maxDrift, $drift);
        }

        $avgDrift = $totalAllocations > 0 ? $totalDrift / $totalAllocations : 0.0;

        return [
            'total_allocations'   => $totalAllocations,
            'average_drift'       => $avgDrift,
            'maximum_drift'       => $maxDrift,
            'needs_rebalancing'   => $this->needsRebalancing($portfolio),
            'performance_summary' => $portfolio['latest_metrics'],
        ];
    }

    /**
     * Check if portfolio needs rebalancing.
     */
    public function needsRebalancing(array $portfolio): bool
    {
        if (empty($portfolio['strategy'])) {
            return false;
        }

        $threshold = $portfolio['strategy']['rebalanceThreshold'] ?? 5.0;

        foreach ($portfolio['asset_allocations'] as $allocation) {
            $drift = $allocation['drift'] ?? 0.0;
            if ($drift > $threshold) {
                return true;
            }
        }

        return false;
    }

    private function validateStrategyStructure(array $strategy): void
    {
        $requiredFields = ['riskProfile', 'rebalanceThreshold', 'targetReturn'];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $strategy)) {
                throw new InvalidArgumentException("Missing required strategy field: {$field}");
            }
        }

        if (! is_string($strategy['riskProfile'])) {
            throw new InvalidArgumentException('Risk profile must be a string');
        }

        if (! is_numeric($strategy['rebalanceThreshold']) || $strategy['rebalanceThreshold'] < 0 || $strategy['rebalanceThreshold'] > 50) {
            throw new InvalidArgumentException('Rebalance threshold must be between 0 and 50');
        }

        if (! is_numeric($strategy['targetReturn']) || $strategy['targetReturn'] < 0) {
            throw new InvalidArgumentException('Target return must be non-negative');
        }
    }

    private function validateAllocationsStructure(array $allocations): void
    {
        $totalWeight = 0.0;

        foreach ($allocations as $allocation) {
            if (! is_array($allocation)) {
                throw new InvalidArgumentException('Each allocation must be an array');
            }

            $requiredFields = ['assetClass', 'targetWeight'];
            foreach ($requiredFields as $field) {
                if (! array_key_exists($field, $allocation)) {
                    throw new InvalidArgumentException("Missing required allocation field: {$field}");
                }
            }

            if (! is_string($allocation['assetClass']) || empty($allocation['assetClass'])) {
                throw new InvalidArgumentException('Asset class must be a non-empty string');
            }

            if (! is_numeric($allocation['targetWeight']) || $allocation['targetWeight'] < 0 || $allocation['targetWeight'] > 100) {
                throw new InvalidArgumentException('Target weight must be between 0 and 100');
            }

            $totalWeight += $allocation['targetWeight'];
        }

        if (abs($totalWeight - 100.0) > 0.01) {
            throw new InvalidArgumentException('Total allocation weights must sum to 100%');
        }
    }

    private function clearPortfoliosCache(string $treasuryId): void
    {
        Cache::forget("portfolios:{$treasuryId}");
    }
}
