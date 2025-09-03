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
        // Only process if entity type is transaction
        if ($event->entityType !== 'transaction') {
            return;
        }

        TransactionMonitoring::updateOrCreate(
            ['transaction_id' => $event->entityId],
            [
                'risk_score'  => $event->riskScore,
                'risk_level'  => $event->riskLevel,
                'status'      => 'analyzing',
                'analyzed_at' => $event->calculatedAt,
            ]
        );
    }

    public function onTransactionFlagged(TransactionFlagged $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if (! $monitoring) {
            $monitoring = TransactionMonitoring::create([
                'transaction_id' => $event->transactionId,
                'status'         => 'flagged',
                'risk_score'     => 75.0, // Default high risk score for flagged transactions
            ]);
        }

        $monitoring->update([
            'status'      => 'flagged',
            'flag_reason' => $event->reason,
            'risk_level'  => $event->severity,
            'patterns'    => $event->details,
            'flagged_at'  => $event->flaggedAt,
        ]);
    }

    public function onTransactionCleared(TransactionCleared $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if (! $monitoring) {
            $monitoring = TransactionMonitoring::create([
                'transaction_id' => $event->transactionId,
                'status'         => 'cleared',
            ]);
        }

        $monitoring->update([
            'status'       => 'cleared',
            'clear_reason' => $event->reason,
            'cleared_at'   => $event->clearedAt,
        ]);
    }

    public function onMonitoringRuleTriggered(MonitoringRuleTriggered $event): void
    {
        // Only process if entity type is transaction
        if ($event->entityType !== 'transaction') {
            return;
        }

        $monitoring = TransactionMonitoring::where('transaction_id', $event->entityId)->first();
        if (! $monitoring) {
            $monitoring = TransactionMonitoring::create([
                'transaction_id'  => $event->entityId,
                'status'          => 'analyzing',
                'triggered_rules' => [],
            ]);
        }

        // Add to triggered_rules array field
        $triggeredRules = $monitoring->triggered_rules ?? [];
        $triggeredRules[] = [
            'rule_id'      => $event->ruleId,
            'rule_name'    => $event->ruleName,
            'context'      => $event->context,
            'triggered_at' => $event->triggeredAt->format('c'),
        ];

        $monitoring->update(['triggered_rules' => $triggeredRules]);

        // Update rule statistics
        $rule = MonitoringRule::find($event->ruleId);
        if ($rule) {
            $rule->increment('trigger_count');
            $rule->update(['last_triggered_at' => $event->triggeredAt]);
        }
    }

    public function onTransactionPatternDetected(TransactionPatternDetected $event): void
    {
        // Process for each transaction in the pattern
        foreach ($event->transactionIds as $transactionId) {
            $monitoring = TransactionMonitoring::where('transaction_id', $transactionId)->first();
            if (! $monitoring) {
                $monitoring = TransactionMonitoring::create([
                    'transaction_id' => $transactionId,
                    'status'         => 'analyzing',
                    'patterns'       => [],
                ]);
            }

            // Update monitoring with patterns
            $patterns = $monitoring->patterns ?? [];
            $patterns[] = [
                'pattern_id'           => $event->patternId,
                'type'                 => $event->patternType,
                'confidence'           => $event->confidenceScore,
                'details'              => $event->details,
                'related_transactions' => $event->transactionIds,
                'detected_at'          => $event->detectedAt->format('c'),
            ];
            $monitoring->update(['patterns' => $patterns]);
        }
    }

    public function onThresholdExceeded(ThresholdExceeded $event): void
    {
        // Only process if entity type is transaction
        if ($event->entityType !== 'transaction') {
            return;
        }

        $monitoring = TransactionMonitoring::where('transaction_id', $event->entityId)->first();
        if (! $monitoring) {
            $monitoring = TransactionMonitoring::create([
                'transaction_id'  => $event->entityId,
                'status'          => 'analyzing',
                'triggered_rules' => [],
            ]);
        }

        // Record threshold violation in triggered_rules
        $triggeredRules = $monitoring->triggered_rules ?? [];
        $triggeredRules[] = [
            'type'            => 'threshold_exceeded',
            'threshold_type'  => $event->thresholdType,
            'threshold_value' => $event->threshold,
            'actual_value'    => $event->currentValue,
            'metadata'        => $event->metadata,
            'exceeded_at'     => $event->exceededAt->format('c'),
        ];

        $monitoring->update(['triggered_rules' => $triggeredRules]);

        // Update monitoring status if critical threshold exceeded
        // Determine severity from metadata or threshold type
        $severity = $event->metadata['severity'] ?? 'medium';
        if ($severity === 'critical' && in_array($monitoring->status, ['analyzing', 'pending'])) {
            $monitoring->update(['status' => 'flagged']);
        }
    }

    public function onTransactionAnalyzed(TransactionAnalyzed $event): void
    {
        $monitoring = TransactionMonitoring::where('transaction_id', $event->transactionId)->first();
        if (! $monitoring) {
            $monitoring = TransactionMonitoring::create([
                'transaction_id' => $event->transactionId,
                'status'         => 'analyzed',
            ]);
        }

        // Calculate risk level based on score
        $riskLevel = match (true) {
            $event->riskScore >= 80 => 'critical',
            $event->riskScore >= 60 => 'high',
            $event->riskScore >= 40 => 'medium',
            $event->riskScore >= 20 => 'low',
            default                 => 'minimal',
        };

        // Store analysis results
        $patterns = $monitoring->patterns ?? [];
        $patterns[] = [
            'type'             => 'analysis_result',
            'risk_score'       => $event->riskScore,
            'analysis_results' => $event->analysisResults,
            'rule_results'     => $event->ruleResults,
            'analyzed_at'      => $event->analyzedAt->format('c'),
        ];

        $monitoring->update([
            'patterns'    => $patterns,
            'risk_score'  => $event->riskScore,
            'risk_level'  => $riskLevel,
            'status'      => $riskLevel === 'high' || $riskLevel === 'critical' ? 'flagged' : 'cleared',
            'analyzed_at' => $event->analyzedAt,
        ]);
    }
}
