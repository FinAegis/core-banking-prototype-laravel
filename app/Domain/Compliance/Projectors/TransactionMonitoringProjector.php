<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Projectors;

use App\Domain\Compliance\Events\MonitoringRuleTriggered;
use App\Domain\Compliance\Events\TransactionPatternDetected;
use App\Domain\Compliance\Events\RiskScoreCalculated;
use App\Domain\Compliance\Events\ThresholdExceeded;
use App\Domain\Compliance\Events\TransactionAnalyzed;
use App\Domain\Compliance\Events\TransactionCleared;
use App\Domain\Compliance\Events\TransactionFlagged;
use App\Domain\Compliance\Models\TransactionMonitoring;
use App\Domain\Compliance\Models\MonitoringRule;
use App\Domain\Compliance\Models\TransactionPattern;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class TransactionMonitoringProjector extends Projector
{
    public function onRiskScoreCalculated(RiskScoreCalculated $event): void
    {
        TransactionMonitoring::updateOrCreate(
            ['transaction_id' => $event->transactionId],
            [
                'risk_score' => $event->riskScore,
                'risk_level' => $event->riskLevel,
                'risk_factors' => $event->factors,
                'metadata' => $event->metadata,
                'status' => 'pending',
                'analyzed_at' => $event->occurredAt ?? now(),
            ]
        );
    }

    public function onTransactionFlagged(TransactionFlagged $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if ($monitoring) {
            $monitoring->update([
                'status' => 'flagged',
                'flag_reason' => $event->reason,
                'flag_severity' => $event->severity,
                'risk_score' => $event->riskScore,
                'patterns' => $event->patterns,
                'flagged_by' => $event->flaggedBy,
                'flagged_at' => $event->occurredAt ?? now(),
            ]);
        }
    }

    public function onTransactionCleared(TransactionCleared $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if ($monitoring) {
            $monitoring->update([
                'status' => 'cleared',
                'clear_reason' => $event->reason,
                'cleared_by' => $event->clearedBy,
                'clear_notes' => $event->notes,
                'cleared_at' => $event->occurredAt ?? now(),
            ]);
        }
    }

    public function onMonitoringRuleTriggered(MonitoringRuleTriggered $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if ($monitoring) {
            // Record the triggered rule
            $monitoring->triggeredRules()->create([
                'rule_id' => $event->ruleId,
                'rule_name' => $event->ruleName,
                'severity' => $event->severity,
                'conditions' => $event->conditions,
                'matched_data' => $event->matchedData,
                'triggered_at' => $event->occurredAt ?? now(),
            ]);
            
            // Update rule statistics
            $rule = MonitoringRule::find($event->ruleId);
            if ($rule) {
                $rule->increment('trigger_count');
                $rule->update(['last_triggered_at' => $event->occurredAt ?? now()]);
            }
        }
    }

    public function onTransactionPatternDetected(TransactionPatternDetected $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if ($monitoring) {
            // Record the detected pattern
            TransactionPattern::create([
                'transaction_id' => $event->transactionId,
                'pattern_type' => $event->patternType,
                'pattern_data' => $event->patternData,
                'confidence' => $event->confidence,
                'related_transactions' => $event->relatedTransactions,
                'detected_at' => $event->occurredAt ?? now(),
            ]);
            
            // Update monitoring with patterns
            $patterns = $monitoring->patterns ?? [];
            $patterns[] = [
                'type' => $event->patternType,
                'confidence' => $event->confidence,
            ];
            $monitoring->update(['patterns' => $patterns]);
        }
    }

    public function onThresholdExceeded(ThresholdExceeded $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if ($monitoring) {
            // Record threshold violation
            $monitoring->thresholdViolations()->create([
                'threshold_type' => $event->thresholdType,
                'threshold_value' => $event->thresholdValue,
                'actual_value' => $event->actualValue,
                'severity' => $event->severity,
                'exceeded_at' => $event->occurredAt ?? now(),
            ]);
            
            // Update monitoring status if critical
            if ($event->severity === 'critical' && $monitoring->status === 'pending') {
                $monitoring->update(['status' => 'reviewing']);
            }
        }
    }

    public function onTransactionAnalyzed(TransactionAnalyzed $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if ($monitoring) {
            $monitoring->update([
                'analysis_id' => $event->analysisId,
                'analysis_results' => $event->results,
                'recommendation' => $event->recommendation,
                'processing_time' => $event->processingTime,
                'status' => 'analyzed',
                'analyzed_at' => $event->occurredAt ?? now(),
            ]);
        }
    }
}
