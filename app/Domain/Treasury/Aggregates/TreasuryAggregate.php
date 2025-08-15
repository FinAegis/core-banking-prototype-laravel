<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Aggregates;

use App\Domain\Treasury\Events\CashAllocated;
use App\Domain\Treasury\Events\RegulatoryReportGenerated;
use App\Domain\Treasury\Events\RiskAssessmentCompleted;
use App\Domain\Treasury\Events\TreasuryAccountCreated;
use App\Domain\Treasury\Events\YieldOptimizationStarted;
use App\Domain\Treasury\Repositories\TreasuryEventRepository;
use App\Domain\Treasury\Repositories\TreasurySnapshotRepository;
use App\Domain\Treasury\ValueObjects\AllocationStrategy;
use App\Domain\Treasury\ValueObjects\RiskProfile;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class TreasuryAggregate extends AggregateRoot
{
    protected string $accountId;

    protected string $name;

    protected string $currency;

    protected string $accountType;

    protected float $balance;

    protected array $allocations = [];

    protected ?AllocationStrategy $currentStrategy = null;

    protected ?RiskProfile $riskProfile = null;

    protected array $activeOptimizations = [];

    protected array $metadata = [];

    protected string $status = 'active';

    public function createAccount(
        string $accountId,
        string $name,
        string $currency,
        string $accountType,
        float $initialBalance,
        array $metadata = []
    ): self {
        if ($initialBalance < 0) {
            throw new InvalidArgumentException('Initial balance cannot be negative');
        }

        $this->recordThat(new TreasuryAccountCreated(
            $accountId,
            $name,
            $currency,
            $accountType,
            $initialBalance,
            $metadata
        ));

        return $this;
    }

    public function allocateCash(
        string $allocationId,
        AllocationStrategy $strategy,
        float $amount,
        string $allocatedBy
    ): self {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Allocation amount must be positive');
        }

        if ($amount > $this->balance) {
            throw new InvalidArgumentException('Insufficient balance for allocation');
        }

        $allocations = $strategy->getDefaultAllocations();

        $this->recordThat(new CashAllocated(
            $this->accountId,
            $allocationId,
            $strategy->getValue(),
            $amount,
            $this->currency,
            $allocations,
            $allocatedBy
        ));

        return $this;
    }

    public function startYieldOptimization(
        string $optimizationId,
        string $strategy,
        float $targetYield,
        RiskProfile $riskProfile,
        array $constraints,
        string $startedBy
    ): self {
        if ($targetYield <= 0) {
            throw new InvalidArgumentException('Target yield must be positive');
        }

        if (! $riskProfile->isAcceptable() && ! $riskProfile->requiresApproval()) {
            throw new InvalidArgumentException('Risk profile exceeds acceptable limits');
        }

        $this->recordThat(new YieldOptimizationStarted(
            $this->accountId,
            $optimizationId,
            $strategy,
            $targetYield,
            $riskProfile->getLevel(),
            $constraints,
            $startedBy
        ));

        return $this;
    }

    public function completeRiskAssessment(
        string $assessmentId,
        RiskProfile $riskProfile,
        array $recommendations,
        string $assessedBy
    ): self {
        $this->recordThat(new RiskAssessmentCompleted(
            $this->accountId,
            $assessmentId,
            $riskProfile->getScore(),
            $riskProfile->getLevel(),
            $riskProfile->getFactors(),
            $recommendations,
            $assessedBy
        ));

        return $this;
    }

    public function generateRegulatoryReport(
        string $reportId,
        string $reportType,
        string $period,
        array $data,
        string $generatedBy
    ): self {
        $this->recordThat(new RegulatoryReportGenerated(
            $this->accountId,
            $reportId,
            $reportType,
            $period,
            $data,
            'generated',
            $generatedBy
        ));

        return $this;
    }

    // Event Handlers
    protected function applyTreasuryAccountCreated(TreasuryAccountCreated $event): void
    {
        $this->accountId = $event->accountId;
        $this->name = $event->name;
        $this->currency = $event->currency;
        $this->accountType = $event->accountType;
        $this->balance = $event->initialBalance;
        $this->metadata = $event->metadata;
    }

    protected function applyCashAllocated(CashAllocated $event): void
    {
        $this->allocations[$event->allocationId] = [
            'strategy'     => $event->strategy,
            'amount'       => $event->amount,
            'allocations'  => $event->allocations,
            'allocated_at' => now(),
        ];

        $this->currentStrategy = new AllocationStrategy($event->strategy, $event->allocations);
    }

    protected function applyYieldOptimizationStarted(YieldOptimizationStarted $event): void
    {
        $this->activeOptimizations[$event->optimizationId] = [
            'strategy'     => $event->strategy,
            'target_yield' => $event->targetYield,
            'risk_profile' => $event->riskProfile,
            'constraints'  => $event->constraints,
            'started_at'   => now(),
            'status'       => 'active',
        ];
    }

    protected function applyRiskAssessmentCompleted(RiskAssessmentCompleted $event): void
    {
        $this->riskProfile = RiskProfile::fromScore(
            $event->riskScore,
            $event->riskFactors
        );
    }

    protected function applyRegulatoryReportGenerated(RegulatoryReportGenerated $event): void
    {
        $this->metadata['last_report'] = [
            'id'           => $event->reportId,
            'type'         => $event->reportType,
            'period'       => $event->period,
            'generated_at' => now(),
        ];
    }

    // Getters
    public function getBalance(): float
    {
        return $this->balance;
    }

    public function getCurrentStrategy(): ?AllocationStrategy
    {
        return $this->currentStrategy;
    }

    public function getRiskProfile(): ?RiskProfile
    {
        return $this->riskProfile;
    }

    public function getActiveOptimizations(): array
    {
        return $this->activeOptimizations;
    }

    public function getAllocations(): array
    {
        return $this->allocations;
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
