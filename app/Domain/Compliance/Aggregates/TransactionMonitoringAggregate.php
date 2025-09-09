<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Aggregates;

use App\Domain\Compliance\Events\MonitoringRuleTriggered;
use App\Domain\Compliance\Events\RiskScoreCalculated;
use App\Domain\Compliance\Events\ThresholdExceeded;
use App\Domain\Compliance\Events\TransactionAnalyzed;
use App\Domain\Compliance\Events\TransactionCleared;
use App\Domain\Compliance\Events\TransactionFlagged;
use App\Domain\Compliance\Events\TransactionPatternDetected;
use App\Domain\Compliance\Repositories\ComplianceEventRepository;
use App\Domain\Compliance\Repositories\ComplianceSnapshotRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Str;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class TransactionMonitoringAggregate extends AggregateRoot
{
    private string $transactionId;

    private string $status = 'pending';

    private float $riskScore = 0.0;

    private string $riskLevel = 'low';

    private array $patterns = [];

    private array $triggeredRules = [];

    private ?string $flagReason = null;

    private ?string $clearReason = null;

    public static function analyzeTransaction(
        string $transactionId,
        float $amount,
        string $fromAccount,
        string $toAccount,
        array $metadata = []
    ): self {
        $monitoring = self::retrieve($transactionId);
        $monitoring->transactionId = $transactionId;

        // Initial risk calculation would happen here
        $riskScore = $monitoring->calculateInitialRiskScore($amount, $metadata);
        $riskLevel = $monitoring->determineRiskLevel($riskScore);

        $monitoring->recordThat(new RiskScoreCalculated(
            $transactionId,
            'transaction',
            $riskScore,
            $riskLevel,
            ['amount' => $amount, 'accounts' => [$fromAccount, $toAccount]],
            new DateTimeImmutable()
        ));

        return $monitoring;
    }

    public function flagTransaction(
        string $reason,
        string $severity,
        ?string $flaggedBy = null
    ): self {
        // Initialize transactionId if not set (for aggregates without prior events)
        if (! isset($this->transactionId)) {
            $this->transactionId = $this->uuid();
        }

        if ($this->status === 'flagged') {
            throw new DomainException('Transaction is already flagged');
        }

        $this->recordThat(new TransactionFlagged(
            $this->transactionId,
            'manual',
            $severity,
            $reason,
            ['risk_score' => $this->riskScore, 'patterns' => $this->patterns, 'flagged_by' => $flaggedBy],
            new DateTimeImmutable()
        ));

        return $this;
    }

    public function clearTransaction(
        string $reason,
        string $clearedBy,
        ?string $notes = null
    ): self {
        // Initialize transactionId if not set (for aggregates without prior events)
        if (! isset($this->transactionId)) {
            $this->transactionId = $this->uuid();
        }

        if (! in_array($this->status, ['flagged', 'reviewing'], true)) {
            throw new DomainException('Transaction cannot be cleared in current status');
        }

        $this->recordThat(new TransactionCleared(
            $this->transactionId,
            $clearedBy,
            $reason,
            ['notes' => $notes],
            new DateTimeImmutable()
        ));

        return $this;
    }

    public function triggerRule(
        string $ruleId,
        string $ruleName,
        string $severity,
        array $conditions,
        array $matchedData
    ): self {
        // Initialize transactionId if not set (for aggregates without prior events)
        if (! isset($this->transactionId)) {
            $this->transactionId = $this->uuid();
        }

        $this->recordThat(new MonitoringRuleTriggered(
            $ruleId,
            $this->transactionId,
            'transaction',
            $ruleName,
            ['severity' => $severity, 'conditions' => $conditions, 'matched_data' => $matchedData],
            new DateTimeImmutable()
        ));

        return $this;
    }

    public function detectPattern(
        string $patternType,
        array $patternData,
        float $confidence,
        array $relatedTransactions = []
    ): self {
        // Initialize transactionId if not set (for aggregates without prior events)
        if (! isset($this->transactionId)) {
            $this->transactionId = $this->uuid();
        }

        $this->recordThat(new TransactionPatternDetected(
            (string) Str::uuid(),
            $patternType,
            array_merge([$this->transactionId], $relatedTransactions),
            $confidence,
            $patternData,
            new DateTimeImmutable()
        ));

        return $this;
    }

    public function exceedThreshold(
        string $thresholdType,
        float $thresholdValue,
        float $actualValue,
        string $severity
    ): self {
        $this->recordThat(new ThresholdExceeded(
            $this->transactionId,
            'transaction',
            $thresholdType,
            $actualValue,
            $thresholdValue,
            ['severity' => $severity],
            new DateTimeImmutable()
        ));

        return $this;
    }

    public function completeAnalysis(
        string $analysisId,
        array $results,
        string $recommendation,
        float $processingTime
    ): self {
        $this->recordThat(new TransactionAnalyzed(
            $this->transactionId,
            $this->riskScore,
            $results,
            ['recommendation' => $recommendation, 'analysis_id' => $analysisId, 'processing_time' => $processingTime],
            new DateTimeImmutable()
        ));

        return $this;
    }

    // Event handlers
    protected function applyRiskScoreCalculated(RiskScoreCalculated $event): void
    {
        $this->riskScore = $event->riskScore;
        $this->riskLevel = $event->riskLevel;
    }

    protected function applyTransactionFlagged(TransactionFlagged $event): void
    {
        $this->status = 'flagged';
        $this->flagReason = $event->reason ?? null;
        if (isset($event->details['risk_score'])) {
            $this->riskScore = $event->details['risk_score'];
        }
        if (isset($event->details['patterns'])) {
            $this->patterns = $event->details['patterns'];
        }
    }

    protected function applyTransactionCleared(TransactionCleared $event): void
    {
        $this->status = 'cleared';
        $this->clearReason = $event->reason ?? null;
        $this->riskLevel = 'low';
    }

    protected function applyMonitoringRuleTriggered(MonitoringRuleTriggered $event): void
    {
        $this->triggeredRules[] = [
            'rule_id'   => $event->ruleId,
            'rule_name' => $event->ruleName,
            'severity'  => $event->context['severity'] ?? 'medium',
        ];

        // Update risk score based on rule severity
        $this->adjustRiskScoreForRule($event->context['severity'] ?? 'medium');
    }

    protected function applyTransactionPatternDetected(TransactionPatternDetected $event): void
    {
        $this->patterns[] = [
            'type'       => $event->patternType,
            'data'       => $event->details,
            'confidence' => $event->confidenceScore,
        ];

        // Adjust risk score based on pattern
        $this->adjustRiskScoreForPattern($event->patternType, $event->confidenceScore);
    }

    protected function applyThresholdExceeded(ThresholdExceeded $event): void
    {
        // Threshold exceeded automatically increases risk
        $this->adjustRiskScoreForThreshold($event->metadata['severity'] ?? 'medium');
    }

    protected function applyTransactionAnalyzed(TransactionAnalyzed $event): void
    {
        $this->status = 'analyzed';
    }

    // Helper methods
    private function calculateInitialRiskScore(float $amount, array $metadata): float
    {
        $score = 0.0;

        // Amount-based risk
        if ($amount > 10000) {
            $score += 20;
        } elseif ($amount > 5000) {
            $score += 10;
        }

        // Add more risk factors as needed

        return min($score, 100.0);
    }

    private function determineRiskLevel(float $score): string
    {
        if ($score >= 75) {
            return 'critical';
        } elseif ($score >= 50) {
            return 'high';
        } elseif ($score >= 25) {
            return 'medium';
        }

        return 'low';
    }

    private function adjustRiskScoreForRule(string $severity): void
    {
        $adjustment = match ($severity) {
            'critical' => 30,
            'high'     => 20,
            'medium'   => 10,
            'low'      => 5,
            default    => 0,
        };

        $this->riskScore = min($this->riskScore + $adjustment, 100.0);
        $this->riskLevel = $this->determineRiskLevel($this->riskScore);
    }

    private function adjustRiskScoreForPattern(string $patternType, float $confidence): void
    {
        $baseAdjustment = match ($patternType) {
            'structuring'     => 25,
            'rapid_movement'  => 20,
            'unusual_pattern' => 15,
            default           => 10,
        };

        $adjustment = $baseAdjustment * $confidence;
        $this->riskScore = min($this->riskScore + $adjustment, 100.0);
        $this->riskLevel = $this->determineRiskLevel($this->riskScore);
    }

    private function adjustRiskScoreForThreshold(string $severity): void
    {
        $adjustment = match ($severity) {
            'critical' => 25,
            'high'     => 15,
            'medium'   => 10,
            'low'      => 5,
            default    => 0,
        };

        $this->riskScore = min($this->riskScore + $adjustment, 100.0);
        $this->riskLevel = $this->determineRiskLevel($this->riskScore);
    }

    // Getters
    public function getTransactionId(): string
    {
        // Initialize transactionId if not set (for aggregates without prior events)
        if (! isset($this->transactionId)) {
            $this->transactionId = $this->uuid();
        }

        return $this->transactionId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRiskScore(): float
    {
        return $this->riskScore;
    }

    public function getRiskLevel(): string
    {
        return $this->riskLevel;
    }

    public function getPatterns(): array
    {
        return $this->patterns;
    }

    public function getTriggeredRules(): array
    {
        return $this->triggeredRules;
    }

    public function getFlagReason(): ?string
    {
        return $this->flagReason;
    }

    public function getClearReason(): ?string
    {
        return $this->clearReason;
    }

    // Override Spatie methods to use our custom repositories
    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app()->make(ComplianceEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app()->make(ComplianceSnapshotRepository::class);
    }
}
