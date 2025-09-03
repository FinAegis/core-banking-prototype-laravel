<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Domain\Account\Models\Transaction;
use App\Domain\Compliance\Aggregates\TransactionMonitoringAggregate;
use App\Domain\Compliance\Events\SuspiciousActivityDetected;
use App\Domain\Compliance\Models\MonitoringRule;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class TransactionMonitoringService
{
    private SuspiciousActivityReportService $sarService;

    private CustomerRiskService $riskService;

    public function __construct(
        SuspiciousActivityReportService $sarService,
        CustomerRiskService $riskService
    ) {
        $this->sarService = $sarService;
        $this->riskService = $riskService;
    }

    /**
     * Analyze a transaction using event sourcing.
     */
    public function analyzeTransaction(Transaction $transaction): array
    {
        return DB::transaction(function () use ($transaction) {
            $startTime = microtime(true);
            try {
                // Extract transaction data from event_properties
                $eventProps = $transaction->event_properties ?? [];
                $amount = $eventProps['amount'] ?? 0;
                $fromAccount = $transaction->aggregate_uuid ?? '';
                $toAccount = $eventProps['destination'] ?? $eventProps['to_account'] ?? '';
                $type = $eventProps['type'] ?? 'transfer';

                // Create or retrieve aggregate
                $aggregate = TransactionMonitoringAggregate::analyzeTransaction(
                    (string) $transaction->id,
                    $amount,
                    $fromAccount,
                    $toAccount,
                    ['type' => $type]
                );

                // Apply monitoring rules
                $ruleResults = $this->applyMonitoringRules($transaction, $aggregate);

                // Detect patterns
                $patterns = $this->detectPatterns($transaction, $aggregate);

                // Check thresholds
                $thresholdResults = $this->checkThresholds($transaction, $aggregate);

                // Determine if transaction should be flagged
                $riskScore = $aggregate->getRiskScore();
                $riskLevel = $aggregate->getRiskLevel();

                if ($riskLevel === 'critical' || $riskScore >= 75) {
                    $aggregate->flagTransaction(
                        'High risk detected',
                        $riskLevel,
                        'system'
                    );

                    // Generate SAR if needed
                    if ($riskScore >= 90) {
                        $this->sarService->createFromTransaction($transaction, [
                            'reason'          => 'Automated: Critical risk score',
                            'risk_score'      => $riskScore,
                            'patterns'        => $patterns,
                            'rules_triggered' => $ruleResults,
                        ]);
                    }

                    Event::dispatch(new SuspiciousActivityDetected(
                        $transaction,
                        ['type' => 'high_risk', 'score' => $riskScore, 'patterns' => $patterns]
                    ));
                }

                // Complete analysis
                $aggregate->completeAnalysis(
                    uniqid('analysis_'),
                    [
                        'rules'      => $ruleResults,
                        'patterns'   => $patterns,
                        'thresholds' => $thresholdResults,
                    ],
                    $this->determineRecommendation($riskLevel),
                    microtime(true) - $startTime
                );

                // Persist aggregate
                $aggregate->persist();

                Log::info('Transaction analyzed', [
                    'transaction_id' => $transaction->id,
                    'risk_score'     => $riskScore,
                    'risk_level'     => $riskLevel,
                    'status'         => $aggregate->getStatus(),
                ]);

                return [
                    'transaction_id'  => $transaction->id,
                    'risk_score'      => $riskScore,
                    'risk_level'      => $riskLevel,
                    'status'          => $aggregate->getStatus(),
                    'patterns'        => $patterns,
                    'rules_triggered' => $ruleResults,
                    'recommendation'  => $this->determineRecommendation($riskLevel),
                ];
            } catch (Exception $e) {
                Log::error('Failed to analyze transaction', [
                    'transaction_id' => $transaction->id,
                    'error'          => $e->getMessage(),
                ]);
                throw $e;
            }
        });
    }

    /**
     * Flag a transaction.
     */
    public function flagTransaction(string $transactionId, string $reason, string $severity = 'medium'): void
    {
        DB::transaction(function () use ($transactionId, $reason, $severity) {
            $aggregate = TransactionMonitoringAggregate::retrieve($transactionId);
            $aggregate->flagTransaction($reason, $severity, (string) (auth()->id() ?? 'system'));
            $aggregate->persist();
        });
    }

    /**
     * Clear a flagged transaction.
     */
    public function clearTransaction(string $transactionId, string $reason, ?string $notes = null): void
    {
        DB::transaction(function () use ($transactionId, $reason, $notes) {
            $aggregate = TransactionMonitoringAggregate::retrieve($transactionId);
            $aggregate->clearTransaction($reason, (string) (auth()->id() ?? 'system'), $notes);
            $aggregate->persist();
        });
    }

    /**
     * Apply monitoring rules to a transaction.
     */
    private function applyMonitoringRules(Transaction $transaction, TransactionMonitoringAggregate $aggregate): array
    {
        $results = [];
        $rules = MonitoringRule::where('is_active', true)->get();
        $eventProps = $transaction->event_properties ?? [];

        foreach ($rules as $rule) {
            if ($this->evaluateRule($rule, $transaction)) {
                $aggregate->triggerRule(
                    (string) $rule->id,
                    $rule->name,
                    $rule->severity ?? 'medium',
                    $rule->conditions ?? [],
                    [
                        'amount' => $eventProps['amount'] ?? 0,
                        'type'   => $eventProps['type'] ?? 'transfer',
                    ]
                );

                $results[] = [
                    'rule_id'   => $rule->id,
                    'rule_name' => $rule->name,
                    'severity'  => $rule->severity ?? 'medium',
                ];
            }
        }

        return $results;
    }

    /**
     * Detect patterns in transaction behavior.
     */
    private function detectPatterns(Transaction $transaction, TransactionMonitoringAggregate $aggregate): array
    {
        $patterns = [];

        // Check for structuring (smurfing)
        $accountId = $transaction->aggregate_uuid ?? null;
        $recentTransactions = $accountId ? Transaction::where('aggregate_uuid', $accountId)
            ->where('created_at', '>=', now()->subHours(24))
            ->get() : collect([]);

        if ($recentTransactions->count() > 5) {
            // Sum amounts from event_properties
            $totalAmount = 0;
            foreach ($recentTransactions as $trans) {
                $props = $trans->event_properties ?? [];
                $totalAmount += $props['amount'] ?? 0;
            }
            if ($totalAmount > 9000 && $totalAmount < 10000) {
                $aggregate->detectPattern(
                    'structuring',
                    [
                        'total_amount'      => $totalAmount,
                        'transaction_count' => $recentTransactions->count(),
                    ],
                    0.8,
                    $recentTransactions->pluck('id')->toArray()
                );

                $patterns[] = [
                    'type'       => 'structuring',
                    'confidence' => 0.8,
                ];
            }
        }

        // Check for rapid movement
        $accountId = $transaction->aggregate_uuid ?? null;
        $rapidTransactions = $accountId ? Transaction::where('aggregate_uuid', $accountId)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->count() : 0;

        if ($rapidTransactions > 3) {
            $aggregate->detectPattern(
                'rapid_movement',
                [
                    'transaction_count' => $rapidTransactions,
                    'time_window'       => '30 minutes',
                ],
                0.7,
                []
            );

            $patterns[] = [
                'type'       => 'rapid_movement',
                'confidence' => 0.7,
            ];
        }

        return $patterns;
    }

    /**
     * Check transaction thresholds.
     */
    private function checkThresholds(Transaction $transaction, TransactionMonitoringAggregate $aggregate): array
    {
        $results = [];

        // Amount threshold
        $amountThresholds = [
            'low'      => 1000,
            'medium'   => 5000,
            'high'     => 10000,
            'critical' => 50000,
        ];

        $eventProps = $transaction->event_properties ?? [];
        $amount = $eventProps['amount'] ?? 0;

        foreach ($amountThresholds as $severity => $threshold) {
            if ($amount >= $threshold) {
                $aggregate->exceedThreshold(
                    'amount',
                    (float) $threshold,
                    (float) $amount,
                    $severity
                );

                $results[] = [
                    'type'      => 'amount',
                    'threshold' => $threshold,
                    'actual'    => $amount,
                    'severity'  => $severity,
                ];

                break; // Only record the highest threshold exceeded
            }
        }

        // Daily limit threshold - sum from event properties
        $accountId = $transaction->aggregate_uuid ?? null;
        $dailyTotal = 0;
        if ($accountId) {
            $todayTransactions = Transaction::where('aggregate_uuid', $accountId)
                ->whereDate('created_at', today())
                ->get();
            foreach ($todayTransactions as $trans) {
                $props = $trans->event_properties ?? [];
                $dailyTotal += $props['amount'] ?? 0;
            }
        }

        $dailyLimit = 100000;
        if ($dailyTotal > $dailyLimit) {
            $aggregate->exceedThreshold(
                'daily_limit',
                (float) $dailyLimit,
                (float) $dailyTotal,
                'high'
            );

            $results[] = [
                'type'      => 'daily_limit',
                'threshold' => $dailyLimit,
                'actual'    => $dailyTotal,
                'severity'  => 'high',
            ];
        }

        return $results;
    }

    /**
     * Evaluate a monitoring rule against a transaction.
     */
    private function evaluateRule(MonitoringRule $rule, Transaction $transaction): bool
    {
        $conditions = $rule->conditions ?? [];

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;

            // Get value from event_properties or transaction attributes
            if ($field === 'amount' || $field === 'type') {
                $eventProps = $transaction->event_properties ?? [];
                $transactionValue = $eventProps[$field] ?? null;
            } else {
                $transactionValue = $transaction->{$field} ?? null;
            }

            if (! $this->evaluateCondition($transactionValue, $operator, $value)) {
                return false; // All conditions must be met
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition.
     */
    private function evaluateCondition($actualValue, string $operator, $expectedValue): bool
    {
        return match ($operator) {
            '='        => $actualValue == $expectedValue,
            '!='       => $actualValue != $expectedValue,
            '>'        => $actualValue > $expectedValue,
            '>='       => $actualValue >= $expectedValue,
            '<'        => $actualValue < $expectedValue,
            '<='       => $actualValue <= $expectedValue,
            'contains' => str_contains((string) $actualValue, (string) $expectedValue),
            'in'       => in_array($actualValue, (array) $expectedValue),
            'not_in'   => ! in_array($actualValue, (array) $expectedValue),
            default    => false,
        };
    }

    /**
     * Determine recommendation based on risk level.
     */
    private function determineRecommendation(string $riskLevel): string
    {
        return match ($riskLevel) {
            'critical' => 'Block transaction and investigate immediately',
            'high'     => 'Flag for manual review',
            'medium'   => 'Monitor closely',
            'low'      => 'Allow transaction',
            default    => 'Allow transaction',
        };
    }

    /**
     * Get monitoring statistics.
     */
    public function getStatistics(array $filters = []): array
    {
        $query = DB::table('transaction_monitorings');

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        return [
            'total_analyzed' => $query->count(),
            'flagged'        => $query->clone()->where('status', 'flagged')->count(),
            'cleared'        => $query->clone()->where('status', 'cleared')->count(),
            'by_risk_level'  => $query->clone()
                ->groupBy('risk_level')
                ->selectRaw('risk_level, count(*) as count')
                ->pluck('count', 'risk_level')
                ->toArray(),
            'average_risk_score' => $query->clone()->avg('risk_score'),
            'patterns_detected'  => $query->clone()
                ->whereNotNull('patterns')
                ->count(),
        ];
    }
}
