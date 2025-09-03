<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Projectors;

use App\Domain\Compliance\Events\MonitoringRuleTriggered;
use App\Domain\Compliance\Events\RiskScoreCalculated;
use App\Domain\Compliance\Events\ThresholdExceeded;
use App\Domain\Compliance\Events\TransactionAnalyzed;
use App\Domain\Compliance\Events\TransactionCleared;
use App\Domain\Compliance\Events\TransactionFlagged;
use App\Domain\Compliance\Events\TransactionPatternDetected;
use App\Domain\Compliance\Models\MonitoringRule;
use App\Domain\Compliance\Models\TransactionMonitoring;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class TransactionMonitoringProjector extends Projector
{
    public function onRiskScoreCalculated(RiskScoreCalculated $event): void
    {
        TransactionMonitoring::updateOrCreate(
            ['transaction_id' => $event->transactionId],
            [
                'risk_score'  => $event->riskScore,
                'risk_level'  => $event->riskLevel,
                'status'      => 'analyzing',
                'analyzed_at' => $event->occurredAt ?? now(),
            ]
        );
    }

    public function onTransactionFlagged(TransactionFlagged $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if (!$monitoring) {
            $monitoring = TransactionMonitoring::create([
                'transaction_id' => $event->transactionId,
                'status'         => 'flagged',
                'risk_score'     => $event->riskScore,
            ]);
        }
        
        $monitoring->update([
            'status'      => 'flagged',
            'flag_reason' => $event->reason,
            'risk_score'  => $event->riskScore,
            'patterns'    => $event->patterns,
            'flagged_at'  => $event->occurredAt ?? now(),
        ]);
    }

    public function onTransactionCleared(TransactionCleared $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if (!$monitoring) {
            $monitoring = TransactionMonitoring::create([
                'transaction_id' => $event->transactionId,
                'status'         => 'cleared',
            ]);
        }
        
        $monitoring->update([
            'status'       => 'cleared',
            'clear_reason' => $event->reason,
            'cleared_at'   => $event->occurredAt ?? now(),
        ]);
    }

    public function onMonitoringRuleTriggered(MonitoringRuleTriggered $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if (!$monitoring) {
            $monitoring = TransactionMonitoring::create([
                'transaction_id'  => $event->transactionId,
                'status'          => 'analyzing',
                'triggered_rules' => [],
            ]);
        }
        
        // Add to triggered_rules array field
        $triggeredRules = $monitoring->triggered_rules ?? [];
        $triggeredRules[] = [
            'rule_id'      => $event->ruleId,
            'rule_name'    => $event->ruleName,
            'severity'     => $event->severity,
            'conditions'   => $event->conditions,
            'matched_data' => $event->matchedData,
            'triggered_at' => ($event->occurredAt ?? now())->format('c'),
        ];
        
        $monitoring->update(['triggered_rules' => $triggeredRules]);

        // Update rule statistics
        $rule = MonitoringRule::find($event->ruleId);
        if ($rule) {
            $rule->increment('trigger_count');
            $rule->update(['last_triggered_at' => $event->occurredAt ?? now()]);
        }
    }

    public function onTransactionPatternDetected(TransactionPatternDetected $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if (!$monitoring) {
            $monitoring = TransactionMonitoring::create([
                'transaction_id' => $event->transactionId,
                'status'         => 'analyzing',
                'patterns'       => [],
            ]);
        }

        // Update monitoring with patterns
        $patterns = $monitoring->patterns ?? [];
        $patterns[] = [
            'type'                 => $event->patternType,
            'confidence'           => $event->confidence,
            'pattern_data'         => $event->patternData,
            'related_transactions' => $event->relatedTransactions,
            'detected_at'          => ($event->occurredAt ?? now())->format('c'),
        ];
        $monitoring->update(['patterns' => $patterns]);
    }

    public function onThresholdExceeded(ThresholdExceeded $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if (!$monitoring) {
            $monitoring = TransactionMonitoring::create([
                'transaction_id'  => $event->transactionId,
                'status'          => 'analyzing',
                'triggered_rules' => [],
            ]);
        }

        // Record threshold violation in triggered_rules
        $triggeredRules = $monitoring->triggered_rules ?? [];
        $triggeredRules[] = [
            'type'            => 'threshold_exceeded',
            'threshold_type'  => $event->thresholdType,
            'threshold_value' => $event->thresholdValue,
            'actual_value'    => $event->actualValue,
            'severity'        => $event->severity,
            'exceeded_at'     => ($event->occurredAt ?? now())->format('c'),
        ];
        
        $monitoring->update(['triggered_rules' => $triggeredRules]);

        // Update monitoring status if critical
        if ($event->severity === 'critical' && in_array($monitoring->status, ['analyzing', 'pending'])) {
            $monitoring->update(['status' => 'flagged']);
        }
    }

    public function onTransactionAnalyzed(TransactionAnalyzed $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if (!$monitoring) {
            $monitoring = TransactionMonitoring::create([
                'transaction_id' => $event->transactionId,
                'status'         => 'analyzed',
            ]);
        }
        
        // Store analysis results in patterns or triggered_rules
        $patterns = $monitoring->patterns ?? [];
        $patterns[] = [
            'type'            => 'analysis_result',
            'analysis_id'     => $event->analysisId,
            'results'         => $event->results,
            'recommendation'  => $event->recommendation,
            'processing_time' => $event->processingTime,
            'analyzed_at'     => ($event->occurredAt ?? now())->format('c'),
        ];
        
        $monitoring->update([
            'patterns'    => $patterns,
            'status'      => $event->recommendation === 'flag' ? 'flagged' : 'cleared',
            'analyzed_at' => $event->occurredAt ?? now(),
        ]);
    }
}
