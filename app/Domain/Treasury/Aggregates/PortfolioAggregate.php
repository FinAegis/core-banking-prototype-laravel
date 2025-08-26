<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Aggregates;

use App\Domain\Treasury\Events\Portfolio\AllocationDriftDetected;
use App\Domain\Treasury\Events\Portfolio\AssetsAllocated;
use App\Domain\Treasury\Events\Portfolio\PerformanceRecorded;
use App\Domain\Treasury\Events\Portfolio\PortfolioCreated;
use App\Domain\Treasury\Events\Portfolio\PortfolioRebalanced;
use App\Domain\Treasury\Events\Portfolio\RebalancingTriggered;
use App\Domain\Treasury\Events\Portfolio\StrategyUpdated;
use App\Domain\Treasury\Repositories\TreasuryEventRepository;
use App\Domain\Treasury\Repositories\TreasurySnapshotRepository;
use App\Domain\Treasury\ValueObjects\AssetAllocation;
use App\Domain\Treasury\ValueObjects\InvestmentStrategy;
use App\Domain\Treasury\ValueObjects\PortfolioMetrics;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class PortfolioAggregate extends AggregateRoot
{
    protected string $portfolioId;

    protected string $treasuryId;

    protected string $name;

    protected ?InvestmentStrategy $strategy = null;

    protected array $assetAllocations = [];

    protected ?PortfolioMetrics $latestMetrics = null;

    protected float $totalValue = 0.0;

    protected string $status = 'active';

    protected array $metadata = [];

    protected bool $isRebalancing = false;

    protected ?string $lastRebalanceDate = null;

    public function createPortfolio(
        string $portfolioId,
        string $treasuryId,
        string $name,
        InvestmentStrategy $strategy,
        array $metadata = []
    ): self {
        if (empty($name)) {
            throw new InvalidArgumentException('Portfolio name cannot be empty');
        }

        if (! $strategy->isValid()) {
            throw new InvalidArgumentException('Invalid investment strategy provided');
        }

        $this->recordThat(new PortfolioCreated(
            $portfolioId,
            $treasuryId,
            $name,
            $strategy->toArray(),
            $metadata
        ));

        return $this;
    }

    public function allocateAssets(
        string $allocationId,
        array $allocations,
        float $totalAmount,
        string $allocatedBy
    ): self {
        if ($totalAmount <= 0) {
            throw new InvalidArgumentException('Total allocation amount must be positive');
        }

        if (empty($allocations)) {
            throw new InvalidArgumentException('Asset allocations cannot be empty');
        }

        // Validate allocations sum to 100%
        $totalPercentage = array_sum(array_column($allocations, 'targetWeight'));
        if (abs($totalPercentage - 100.0) > 0.01) {
            throw new InvalidArgumentException('Asset allocations must sum to 100%');
        }

        // Convert allocation data to AssetAllocation value objects
        $assetAllocations = [];
        foreach ($allocations as $allocation) {
            $assetAllocations[] = new AssetAllocation(
                $allocation['assetClass'],
                $allocation['targetWeight'],
                $allocation['currentWeight'] ?? 0.0,
                $allocation['drift'] ?? 0.0
            );
        }

        $this->recordThat(new AssetsAllocated(
            $this->portfolioId,
            $allocationId,
            $assetAllocations,
            $totalAmount,
            $allocatedBy
        ));

        return $this;
    }

    public function rebalancePortfolio(
        string $rebalanceId,
        array $targetAllocations,
        string $reason,
        string $initiatedBy
    ): self {
        if ($this->isRebalancing) {
            throw new InvalidArgumentException('Portfolio is already being rebalanced');
        }

        if (empty($targetAllocations)) {
            throw new InvalidArgumentException('Target allocations cannot be empty');
        }

        // Validate target allocations sum to 100%
        $totalPercentage = array_sum(array_column($targetAllocations, 'targetWeight'));
        if (abs($totalPercentage - 100.0) > 0.01) {
            throw new InvalidArgumentException('Target allocations must sum to 100%');
        }

        $this->recordThat(new PortfolioRebalanced(
            $this->portfolioId,
            $rebalanceId,
            $targetAllocations,
            $this->assetAllocations, // Previous allocations
            $reason,
            $initiatedBy
        ));

        return $this;
    }

    public function updateStrategy(
        InvestmentStrategy $newStrategy,
        string $reason,
        string $updatedBy
    ): self {
        if (! $newStrategy->isValid()) {
            throw new InvalidArgumentException('Invalid investment strategy provided');
        }

        if ($this->strategy && $this->strategy->equals($newStrategy)) {
            throw new InvalidArgumentException('New strategy is identical to current strategy');
        }

        $this->recordThat(new StrategyUpdated(
            $this->portfolioId,
            $this->strategy?->toArray() ?? [],
            $newStrategy->toArray(),
            $reason,
            $updatedBy
        ));

        return $this;
    }

    public function recordPerformance(
        string $metricsId,
        PortfolioMetrics $metrics,
        string $period,
        string $recordedBy
    ): self {
        if (! $metrics->isValid()) {
            throw new InvalidArgumentException('Invalid portfolio metrics provided');
        }

        $this->recordThat(new PerformanceRecorded(
            $this->portfolioId,
            $metricsId,
            $metrics->toArray(),
            $period,
            $recordedBy
        ));

        // Check for rebalancing triggers if strategy exists
        if ($this->strategy && $this->shouldTriggerRebalancing($metrics)) {
            $this->recordThat(new RebalancingTriggered(
                $this->portfolioId,
                $this->calculateDriftLevels(),
                'automatic_drift_threshold',
                'system'
            ));
        }

        return $this;
    }

    public function detectAllocationDrift(array $driftLevels, string $detectedBy): self
    {
        if (empty($driftLevels)) {
            throw new InvalidArgumentException('Drift levels cannot be empty');
        }

        $maxDrift = max(array_column($driftLevels, 'drift'));
        if ($this->strategy && $maxDrift > $this->strategy->getRebalanceThreshold()) {
            $this->recordThat(new AllocationDriftDetected(
                $this->portfolioId,
                $driftLevels,
                $maxDrift,
                $this->strategy->getRebalanceThreshold(),
                $detectedBy
            ));
        }

        return $this;
    }

    // Event Handlers
    protected function applyPortfolioCreated(PortfolioCreated $event): void
    {
        $this->portfolioId = $event->portfolioId;
        $this->treasuryId = $event->treasuryId;
        $this->name = $event->name;
        $this->strategy = InvestmentStrategy::fromArray($event->strategy);
        $this->metadata = $event->metadata;
        $this->status = 'active';
    }

    protected function applyAssetsAllocated(AssetsAllocated $event): void
    {
        $this->assetAllocations = $event->allocations;
        $this->totalValue = $event->totalAmount;
        $this->isRebalancing = false;
    }

    protected function applyPortfolioRebalanced(PortfolioRebalanced $event): void
    {
        // Update allocations with new targets
        foreach ($event->targetAllocations as $targetAllocation) {
            foreach ($this->assetAllocations as &$currentAllocation) {
                if ($currentAllocation->getAssetClass() === $targetAllocation['assetClass']) {
                    $currentAllocation = new AssetAllocation(
                        $currentAllocation->getAssetClass(),
                        $targetAllocation['targetWeight'],
                        $targetAllocation['targetWeight'], // Set current to target after rebalancing
                        0.0 // Reset drift after rebalancing
                    );
                }
            }
        }

        $this->lastRebalanceDate = now()->toISOString();
        $this->isRebalancing = false;
    }

    protected function applyStrategyUpdated(StrategyUpdated $event): void
    {
        $this->strategy = InvestmentStrategy::fromArray($event->newStrategy);
    }

    protected function applyPerformanceRecorded(PerformanceRecorded $event): void
    {
        $this->latestMetrics = PortfolioMetrics::fromArray($event->metrics);
        $this->totalValue = $this->latestMetrics->getTotalValue();
    }

    protected function applyRebalancingTriggered(RebalancingTriggered $event): void
    {
        $this->isRebalancing = true;
    }

    protected function applyAllocationDriftDetected(AllocationDriftDetected $event): void
    {
        $this->isRebalancing = true;

        // Update drift levels in current allocations
        foreach ($event->driftLevels as $driftData) {
            foreach ($this->assetAllocations as &$allocation) {
                if ($allocation->getAssetClass() === $driftData['assetClass']) {
                    $allocation = new AssetAllocation(
                        $allocation->getAssetClass(),
                        $allocation->getTargetWeight(),
                        $driftData['currentWeight'],
                        $driftData['drift']
                    );
                }
            }
        }
    }

    // Helper methods
    private function shouldTriggerRebalancing(PortfolioMetrics $metrics): bool
    {
        if (! $this->strategy) {
            return false;
        }

        $driftLevels = $this->calculateDriftLevels();
        if (empty($driftLevels)) {
            return false;
        }

        $driftValues = array_column($driftLevels, 'drift');
        if (empty($driftValues)) {
            return false;
        }

        $maxDrift = max($driftValues);

        return $maxDrift > $this->strategy->getRebalanceThreshold();
    }

    private function calculateDriftLevels(): array
    {
        $driftLevels = [];
        foreach ($this->assetAllocations as $allocation) {
            $driftLevels[] = [
                'assetClass'    => $allocation->getAssetClass(),
                'currentWeight' => $allocation->getCurrentWeight(),
                'targetWeight'  => $allocation->getTargetWeight(),
                'drift'         => $allocation->getDrift(),
            ];
        }

        return $driftLevels;
    }

    // Getters
    public function getPortfolioId(): string
    {
        return $this->portfolioId;
    }

    public function getTreasuryId(): string
    {
        return $this->treasuryId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStrategy(): ?InvestmentStrategy
    {
        return $this->strategy;
    }

    public function getAssetAllocations(): array
    {
        return $this->assetAllocations;
    }

    public function getLatestMetrics(): ?PortfolioMetrics
    {
        return $this->latestMetrics;
    }

    public function getTotalValue(): float
    {
        return $this->totalValue;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isRebalancing(): bool
    {
        return $this->isRebalancing;
    }

    public function getLastRebalanceDate(): ?string
    {
        return $this->lastRebalanceDate;
    }

    // Custom repository methods for separate event storage
    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app()->make(TreasuryEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app()->make(TreasurySnapshotRepository::class);
    }
}
