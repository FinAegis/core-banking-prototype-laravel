<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Events\ReputationBoosted;
use App\Domain\AgentProtocol\Events\ReputationDecayed;
use App\Domain\AgentProtocol\Events\ReputationInitialized;
use App\Domain\AgentProtocol\Events\ReputationPenaltyApplied;
use App\Domain\AgentProtocol\Events\ReputationUpdated;
use App\Domain\AgentProtocol\Events\TrustLevelChanged;
use App\Domain\AgentProtocol\Repositories\AgentProtocolEventRepository;
use App\Domain\AgentProtocol\Repositories\AgentProtocolSnapshotRepository;
use Carbon\Carbon;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class ReputationAggregate extends AggregateRoot
{
    protected string $reputationId = '';

    protected string $agentId = '';

    protected float $score = 50.0; // 0-100 scale

    protected string $trustLevel = 'neutral'; // untrusted, low, neutral, high, trusted

    protected array $transactionHistory = [];

    protected array $disputeHistory = [];

    protected array $boostHistory = [];

    protected ?Carbon $lastDecayAt = null;

    protected ?Carbon $lastTransactionAt = null;

    protected int $totalTransactions = 0;

    protected int $successfulTransactions = 0;

    protected int $failedTransactions = 0;

    protected int $disputedTransactions = 0;

    protected array $metadata = [];

    protected string $status = 'active';

    protected const DECAY_RATE = 0.01; // 1% per period

    protected const MIN_SCORE = 0.0;

    protected const MAX_SCORE = 100.0;

    protected const TRUST_LEVELS = [
        'untrusted' => [0, 20],
        'low'       => [20, 40],
        'neutral'   => [40, 60],
        'high'      => [60, 80],
        'trusted'   => [80, 100],
    ];

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(AgentProtocolEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(AgentProtocolSnapshotRepository::class);
    }

    public static function initializeReputation(
        string $reputationId,
        string $agentId,
        float $initialScore = 50.0,
        array $metadata = []
    ): self {
        if ($initialScore < self::MIN_SCORE || $initialScore > self::MAX_SCORE) {
            throw new InvalidArgumentException('Initial score must be between {self::MIN_SCORE} and {self::MAX_SCORE}');
        }

        $aggregate = static::retrieve($reputationId);

        $trustLevel = $aggregate->calculateTrustLevelFromScore($initialScore);

        $aggregate->recordThat(new ReputationInitialized(
            reputationId: $reputationId,
            agentId: $agentId,
            initialScore: $initialScore,
            trustLevel: $trustLevel,
            metadata: $metadata
        ));

        return $aggregate;
    }

    public function recordTransaction(
        string $transactionId,
        string $outcome,
        float $value,
        array $metadata = []
    ): self {
        $scoreChange = $this->calculateScoreChange($outcome, $value);
        $newScore = $this->clampScore($this->score + $scoreChange);
        $newTrustLevel = $this->calculateTrustLevelFromScore($newScore);

        $this->recordThat(new ReputationUpdated(
            reputationId: $this->reputationId,
            agentId: $this->agentId,
            transactionId: $transactionId,
            previousScore: $this->score,
            newScore: $newScore,
            scoreChange: $scoreChange,
            outcome: $outcome,
            value: $value,
            metadata: $metadata
        ));

        if ($newTrustLevel !== $this->trustLevel) {
            $this->recordThat(new TrustLevelChanged(
                reputationId: $this->reputationId,
                agentId: $this->agentId,
                previousLevel: $this->trustLevel,
                newLevel: $newTrustLevel,
                score: $newScore,
                reason: "transaction_{$outcome}",
                metadata: ['transaction_id' => $transactionId]
            ));
        }

        return $this;
    }

    public function applyDisputePenalty(
        string $disputeId,
        string $severity,
        string $reason,
        array $metadata = []
    ): self {
        $penalty = $this->calculateDisputePenalty($severity);
        $newScore = $this->clampScore($this->score - $penalty);
        $newTrustLevel = $this->calculateTrustLevelFromScore($newScore);

        $this->recordThat(new ReputationPenaltyApplied(
            reputationId: $this->reputationId,
            agentId: $this->agentId,
            disputeId: $disputeId,
            previousScore: $this->score,
            newScore: $newScore,
            penalty: $penalty,
            severity: $severity,
            reason: $reason,
            metadata: $metadata
        ));

        if ($newTrustLevel !== $this->trustLevel) {
            $this->recordThat(new TrustLevelChanged(
                reputationId: $this->reputationId,
                agentId: $this->agentId,
                previousLevel: $this->trustLevel,
                newLevel: $newTrustLevel,
                score: $newScore,
                reason: "dispute_penalty_{$severity}",
                metadata: ['dispute_id' => $disputeId]
            ));
        }

        return $this;
    }

    public function applyReputationBoost(
        string $reason,
        float $amount,
        array $metadata = []
    ): self {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Boost amount must be positive');
        }

        $newScore = $this->clampScore($this->score + $amount);
        $newTrustLevel = $this->calculateTrustLevelFromScore($newScore);

        $this->recordThat(new ReputationBoosted(
            reputationId: $this->reputationId,
            agentId: $this->agentId,
            previousScore: $this->score,
            newScore: $newScore,
            boostAmount: $amount,
            reason: $reason,
            metadata: $metadata
        ));

        if ($newTrustLevel !== $this->trustLevel) {
            $this->recordThat(new TrustLevelChanged(
                reputationId: $this->reputationId,
                agentId: $this->agentId,
                previousLevel: $this->trustLevel,
                newLevel: $newTrustLevel,
                score: $newScore,
                reason: "boost_{$reason}",
                metadata: $metadata
            ));
        }

        return $this;
    }

    public function decayReputation(int $daysSinceLastActivity): self
    {
        if ($daysSinceLastActivity <= 0) {
            return $this;
        }

        // Calculate decay based on inactivity period
        $decayFactor = self::DECAY_RATE * $daysSinceLastActivity;
        $decayAmount = $this->score * min($decayFactor, 0.5); // Max 50% decay

        if ($decayAmount < 0.01) {
            return $this; // Skip negligible decay
        }

        $newScore = $this->clampScore($this->score - $decayAmount);
        $newTrustLevel = $this->calculateTrustLevelFromScore($newScore);

        $this->recordThat(new ReputationDecayed(
            reputationId: $this->reputationId,
            agentId: $this->agentId,
            previousScore: $this->score,
            newScore: $newScore,
            decayAmount: $decayAmount,
            daysSinceLastActivity: $daysSinceLastActivity,
            decayRate: self::DECAY_RATE
        ));

        if ($newTrustLevel !== $this->trustLevel) {
            $this->recordThat(new TrustLevelChanged(
                reputationId: $this->reputationId,
                agentId: $this->agentId,
                previousLevel: $this->trustLevel,
                newLevel: $newTrustLevel,
                score: $newScore,
                reason: 'inactivity_decay',
                metadata: ['days_inactive' => $daysSinceLastActivity]
            ));
        }

        return $this;
    }

    public function calculateTrustLevel(): string
    {
        return $this->calculateTrustLevelFromScore($this->score);
    }

    protected function calculateTrustLevelFromScore(float $score): string
    {
        foreach (self::TRUST_LEVELS as $level => [$min, $max]) {
            if ($score >= $min && $score < $max) {
                return $level;
            }
        }

        return $score >= self::MAX_SCORE ? 'trusted' : 'untrusted';
    }

    protected function calculateScoreChange(string $outcome, float $value): float
    {
        // Weight score changes based on transaction value and outcome
        $baseChange = match ($outcome) {
            'success'   => 1.0,
            'failed'    => -2.0,
            'cancelled' => -0.5,
            'timeout'   => -1.0,
            default     => 0.0,
        };

        // Scale by transaction value (log scale for large values)
        $valueFactor = min(log10($value + 1) / 3, 2.0);

        return $baseChange * $valueFactor;
    }

    protected function calculateDisputePenalty(string $severity): float
    {
        return match ($severity) {
            'minor'    => 5.0,
            'moderate' => 10.0,
            'major'    => 20.0,
            'critical' => 30.0,
            default    => 10.0,
        };
    }

    protected function clampScore(float $score): float
    {
        return max(self::MIN_SCORE, min(self::MAX_SCORE, $score));
    }

    // Event application methods
    protected function applyReputationInitialized(ReputationInitialized $event): void
    {
        $this->reputationId = $event->reputationId;
        $this->agentId = $event->agentId;
        $this->score = $event->initialScore;
        $this->trustLevel = $event->trustLevel;
        $this->metadata = $event->metadata;
        $this->lastDecayAt = Carbon::now();
    }

    protected function applyReputationUpdated(ReputationUpdated $event): void
    {
        $this->score = $event->newScore;
        $this->lastTransactionAt = Carbon::now();
        $this->totalTransactions++;

        $this->transactionHistory[] = [
            'transaction_id' => $event->transactionId,
            'outcome'        => $event->outcome,
            'value'          => $event->value,
            'score_change'   => $event->scoreChange,
            'timestamp'      => Carbon::now()->toIso8601String(),
        ];

        match ($event->outcome) {
            'success'  => $this->successfulTransactions++,
            'failed'   => $this->failedTransactions++,
            'disputed' => $this->disputedTransactions++,
            default    => null
        };
    }

    protected function applyReputationPenaltyApplied(ReputationPenaltyApplied $event): void
    {
        $this->score = $event->newScore;

        $this->disputeHistory[] = [
            'dispute_id' => $event->disputeId,
            'severity'   => $event->severity,
            'penalty'    => $event->penalty,
            'reason'     => $event->reason,
            'timestamp'  => Carbon::now()->toIso8601String(),
        ];

        $this->disputedTransactions++;
    }

    protected function applyReputationBoosted(ReputationBoosted $event): void
    {
        $this->score = $event->newScore;

        $this->boostHistory[] = [
            'amount'    => $event->boostAmount,
            'reason'    => $event->reason,
            'timestamp' => Carbon::now()->toIso8601String(),
        ];
    }

    protected function applyReputationDecayed(ReputationDecayed $event): void
    {
        $this->score = $event->newScore;
        $this->lastDecayAt = Carbon::now();
    }

    protected function applyTrustLevelChanged(TrustLevelChanged $event): void
    {
        $this->trustLevel = $event->newLevel;
    }

    // Getters for read operations
    public function getScore(): float
    {
        return $this->score;
    }

    public function getTrustLevel(): string
    {
        return $this->trustLevel;
    }

    public function getAgentId(): string
    {
        return $this->agentId;
    }

    public function getStats(): array
    {
        return [
            'total_transactions'      => $this->totalTransactions,
            'successful_transactions' => $this->successfulTransactions,
            'failed_transactions'     => $this->failedTransactions,
            'disputed_transactions'   => $this->disputedTransactions,
            'success_rate'            => $this->totalTransactions > 0
                ? ($this->successfulTransactions / $this->totalTransactions) * 100
                : 0,
        ];
    }
}
