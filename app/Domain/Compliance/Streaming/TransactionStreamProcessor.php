<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Streaming;

use App\Domain\Account\Models\Transaction;
use App\Domain\Compliance\Events\PatternDetected;
use App\Domain\Compliance\Events\RealTimeAlertGenerated;
use App\Domain\Compliance\Services\TransactionMonitoringService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Real-time transaction stream processor for compliance monitoring.
 * Processes transactions as they occur and detects patterns in real-time.
 */
class TransactionStreamProcessor implements ShouldQueue
{
    use InteractsWithQueue;

    private const WINDOW_SIZE = 100; // Number of transactions to keep in memory

    private const TIME_WINDOW = 3600; // Seconds to keep transactions in cache

    private TransactionMonitoringService $monitoringService;

    private PatternDetectionEngine $patternEngine;

    public function __construct(
        TransactionMonitoringService $monitoringService,
        PatternDetectionEngine $patternEngine
    ) {
        $this->monitoringService = $monitoringService;
        $this->patternEngine = $patternEngine;
    }

    /**
     * Process a single transaction in real-time.
     */
    public function processTransaction(Transaction $transaction): array
    {
        $startTime = microtime(true);
        $results = [
            'transaction_id' => $transaction->id,
            'processed_at'   => now()->toIso8601String(),
            'alerts'         => [],
            'patterns'       => [],
            'actions'        => [],
        ];

        try {
            // Step 1: Basic monitoring (existing service)
            $monitoringResult = $this->monitoringService->monitorTransaction($transaction);
            $results['alerts'] = $monitoringResult['alerts'];
            $results['actions'] = $monitoringResult['actions'];

            // Step 2: Add to stream buffer for pattern detection
            $this->addToStreamBuffer($transaction);

            // Step 3: Real-time pattern detection
            $patterns = $this->detectStreamPatterns($transaction);
            if (! empty($patterns)) {
                $results['patterns'] = $patterns;
                $this->handleDetectedPatterns($patterns, $transaction);
            }

            // Step 4: Check velocity and frequency patterns
            $velocityAlerts = $this->checkVelocityPatterns($transaction);
            if (! empty($velocityAlerts)) {
                $results['alerts'] = array_merge($results['alerts'], $velocityAlerts);
            }

            // Step 5: Cross-reference with ongoing cases
            $this->crossReferenceWithCases($transaction, $results);

            // Step 6: Update risk metrics in real-time
            $this->updateRealTimeMetrics($transaction, $results);

            // Log processing time for performance monitoring
            $processingTime = (microtime(true) - $startTime) * 1000;
            if ($processingTime > 100) { // Log if processing takes more than 100ms
                Log::warning('Slow transaction processing', [
                    'transaction_id'     => $transaction->id,
                    'processing_time_ms' => $processingTime,
                ]);
            }

            // Emit real-time event if alerts or patterns detected
            if (! empty($results['alerts']) || ! empty($results['patterns'])) {
                Event::dispatch(new RealTimeAlertGenerated($transaction, $results));
            }
        } catch (Exception $e) {
            Log::error('Stream processing failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
            ]);

            $results['error'] = $e->getMessage();
            $results['status'] = 'failed';
        }

        return $results;
    }

    /**
     * Process a batch of transactions efficiently.
     */
    public function processBatch(Collection $transactions): array
    {
        $results = [];

        // Sort by timestamp to maintain chronological order
        $transactions = $transactions->sortBy('created_at');

        foreach ($transactions as $transaction) {
            $results[$transaction->id] = $this->processTransaction($transaction);
        }

        // Detect cross-transaction patterns
        $batchPatterns = $this->detectBatchPatterns($transactions, $results);
        if (! empty($batchPatterns)) {
            $this->handleBatchPatterns($batchPatterns, $transactions);
        }

        return $results;
    }

    /**
     * Add transaction to stream buffer for pattern detection.
     */
    private function addToStreamBuffer(Transaction $transaction): void
    {
        // Ensure the account relationship is loaded
        if (! $transaction->relationLoaded('account')) {
            $transaction->load('account');
        }

        $account = $transaction->account;
        if (! $account) {
            return;
        }

        $bufferKey = "stream_buffer:{$account->id}";
        $buffer = Cache::get($bufferKey, []);

        // Add new transaction
        $buffer[] = [
            'id'        => $transaction->id,
            'amount'    => $transaction->event_properties['amount'] ?? 0,
            'type'      => $transaction->event_properties['type'] ?? 'unknown',
            'timestamp' => $transaction->created_at->timestamp,
            'metadata'  => $transaction->event_properties['metadata'] ?? [],
        ];

        // Keep only recent transactions (sliding window)
        $buffer = $this->maintainSlidingWindow($buffer);

        // Store in cache
        Cache::put($bufferKey, $buffer, self::TIME_WINDOW);
    }

    /**
     * Maintain sliding window of transactions.
     */
    private function maintainSlidingWindow(array $buffer): array
    {
        // Remove old transactions beyond time window
        $cutoffTime = (int) now()->timestamp - self::TIME_WINDOW;
        $buffer = array_filter($buffer, fn ($item) => $item['timestamp'] > $cutoffTime);

        // Keep only the most recent N transactions
        if (count($buffer) > self::WINDOW_SIZE) {
            $buffer = array_slice($buffer, -self::WINDOW_SIZE);
        }

        return array_values($buffer);
    }

    /**
     * Detect patterns in transaction stream.
     */
    private function detectStreamPatterns(Transaction $transaction): array
    {
        $patterns = [];

        // Ensure the account relationship is loaded
        if (! $transaction->relationLoaded('account')) {
            $transaction->load('account');
        }

        $account = $transaction->account;

        if (! $account) {
            return $patterns;
        }

        $bufferKey = "stream_buffer:{$account->id}";
        $buffer = Cache::get($bufferKey, []);

        if (count($buffer) < 3) {
            return $patterns; // Need minimum transactions for pattern detection
        }

        // Use pattern detection engine
        $detectedPatterns = $this->patternEngine->analyzeStream($buffer, $transaction);

        foreach ($detectedPatterns as $pattern) {
            if ($pattern['confidence'] >= 0.7) { // Only high confidence patterns
                $patterns[] = [
                    'type'        => $pattern['type'],
                    'confidence'  => $pattern['confidence'],
                    'description' => $pattern['description'],
                    'risk_score'  => $pattern['risk_score'],
                    'evidence'    => $pattern['evidence'],
                    'detected_at' => now()->toIso8601String(),
                ];
            }
        }

        return $patterns;
    }

    /**
     * Check velocity patterns in real-time.
     */
    private function checkVelocityPatterns(Transaction $transaction): array
    {
        $alerts = [];

        // Ensure the account relationship is loaded
        if (! $transaction->relationLoaded('account')) {
            $transaction->load('account');
        }

        $account = $transaction->account;

        if (! $account) {
            return $alerts;
        }

        // Check various velocity metrics
        $velocityMetrics = [
            '1min'  => ['window' => 60, 'max_count' => 5, 'max_amount' => 10000],
            '5min'  => ['window' => 300, 'max_count' => 10, 'max_amount' => 25000],
            '1hour' => ['window' => 3600, 'max_count' => 30, 'max_amount' => 100000],
        ];

        foreach ($velocityMetrics as $period => $limits) {
            $key = "velocity:{$account->id}:{$period}";
            $velocity = Cache::get($key, ['count' => 0, 'amount' => 0]);

            // Update velocity
            $velocity['count']++;
            $amount = $transaction->event_properties['amount'] ?? 0;
            $velocity['amount'] += is_numeric($amount) ? (float) $amount : 0;

            // Check limits
            if ($velocity['count'] > $limits['max_count'] || (float) $velocity['amount'] > (float) $limits['max_amount']) {
                $alerts[] = [
                    'type'     => 'velocity_exceeded',
                    'period'   => $period,
                    'count'    => $velocity['count'],
                    'amount'   => $velocity['amount'],
                    'limits'   => $limits,
                    'severity' => $this->calculateVelocitySeverity($velocity, $limits),
                ];
            }

            // Store updated velocity
            Cache::put($key, $velocity, $limits['window']);
        }

        return $alerts;
    }

    /**
     * Calculate velocity alert severity.
     */
    private function calculateVelocitySeverity(array $velocity, array $limits): string
    {
        $countRatio = $velocity['count'] / $limits['max_count'];
        $amountRatio = $velocity['amount'] / $limits['max_amount'];
        $maxRatio = max($countRatio, $amountRatio);

        if ($maxRatio > 2) {
            return 'critical';
        } elseif ($maxRatio > 1.5) {
            return 'high';
        } elseif ($maxRatio > 1.2) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Cross-reference with ongoing compliance cases.
     */
    private function crossReferenceWithCases(Transaction $transaction, array &$results): void
    {
        // Ensure the account relationship is loaded
        if (! $transaction->relationLoaded('account')) {
            $transaction->load('account');
        }

        $account = $transaction->account;
        if (! $account || ! $account->user) {
            return;
        }

        // Check if user has ongoing cases
        $ongoingCasesKey = "ongoing_cases:{$account->user->id}";
        $cases = Cache::get($ongoingCasesKey, []);

        if (! empty($cases)) {
            $results['related_cases'] = $cases;
            $results['requires_enhanced_monitoring'] = true;

            // Add case reference to alerts
            foreach ($results['alerts'] as &$alert) {
                $alert['related_cases'] = $cases;
            }
        }
    }

    /**
     * Update real-time risk metrics.
     */
    private function updateRealTimeMetrics(Transaction $transaction, array $results): void
    {
        // Ensure the account relationship is loaded
        if (! $transaction->relationLoaded('account')) {
            $transaction->load('account');
        }

        $account = $transaction->account;
        if (! $account) {
            return;
        }

        $metricsKey = "risk_metrics:{$account->id}";
        $metrics = Cache::get($metricsKey, [
            'transaction_count' => 0,
            'alert_count'       => 0,
            'pattern_count'     => 0,
            'risk_score'        => 0,
            'last_updated'      => null,
        ]);

        // Update metrics
        $metrics['transaction_count']++;
        $metrics['alert_count'] += count($results['alerts']);
        $metrics['pattern_count'] += count($results['patterns']);

        // Calculate dynamic risk score
        $metrics['risk_score'] = $this->calculateDynamicRiskScore($metrics, $results);
        $metrics['last_updated'] = now()->toIso8601String();

        // Store metrics
        Cache::put($metricsKey, $metrics, 86400); // 24 hours

        // Check if risk threshold exceeded
        if ($metrics['risk_score'] > 75) {
            $this->escalateHighRisk($account, $metrics);
        }
    }

    /**
     * Calculate dynamic risk score based on recent activity.
     */
    private function calculateDynamicRiskScore(array $metrics, array $results): float
    {
        $baseScore = 0;

        // Factor in alert frequency
        if ($metrics['transaction_count'] > 0) {
            $alertRatio = $metrics['alert_count'] / $metrics['transaction_count'];
            $baseScore += $alertRatio * 30;
        }

        // Factor in pattern detection
        $baseScore += min($metrics['pattern_count'] * 10, 30);

        // Factor in current alerts
        foreach ($results['alerts'] as $alert) {
            if (isset($alert['severity'])) {
                $baseScore += match ($alert['severity']) {
                    'critical' => 20,
                    'high'     => 15,
                    'medium'   => 10,
                    'low'      => 5,
                    default    => 0,
                };
            }
        }

        // Factor in patterns
        foreach ($results['patterns'] as $pattern) {
            $baseScore += ($pattern['risk_score'] ?? 0) * ($pattern['confidence'] ?? 1);
        }

        return min($baseScore, 100); // Cap at 100
    }

    /**
     * Handle detected patterns.
     */
    private function handleDetectedPatterns(array $patterns, Transaction $transaction): void
    {
        foreach ($patterns as $pattern) {
            Event::dispatch(new PatternDetected($transaction, $pattern));

            // Log pattern for analysis
            Log::info('Pattern detected in stream', [
                'transaction_id' => $transaction->id,
                'pattern'        => $pattern,
            ]);
        }
    }

    /**
     * Detect patterns across batch of transactions.
     */
    private function detectBatchPatterns(Collection $transactions, array $results): array
    {
        // Group by account for pattern detection
        $byAccount = $transactions->groupBy('account_id');
        $patterns = [];

        foreach ($byAccount as $accountId => $accountTransactions) {
            $accountPatterns = $this->patternEngine->analyzeBatch($accountTransactions);
            if (! empty($accountPatterns)) {
                $patterns[$accountId] = $accountPatterns;
            }
        }

        return $patterns;
    }

    /**
     * Handle batch patterns.
     */
    private function handleBatchPatterns(array $patterns, Collection $transactions): void
    {
        foreach ($patterns as $accountId => $accountPatterns) {
            foreach ($accountPatterns as $pattern) {
                Log::info('Batch pattern detected', [
                    'account_id'        => $accountId,
                    'pattern'           => $pattern,
                    'transaction_count' => count($pattern['transactions'] ?? []),
                ]);
            }
        }
    }

    /**
     * Escalate high risk accounts.
     */
    private function escalateHighRisk(object $account, array $metrics): void
    {
        Log::warning('High risk account detected', [
            'account_id' => $account->id,
            'risk_score' => $metrics['risk_score'],
            'metrics'    => $metrics,
        ]);

        // Add to high risk monitoring list
        $highRiskKey = 'high_risk_accounts';
        $highRiskAccounts = Cache::get($highRiskKey, []);
        $highRiskAccounts[$account->id] = [
            'added_at'   => now()->toIso8601String(),
            'risk_score' => $metrics['risk_score'],
            'metrics'    => $metrics,
        ];
        Cache::put($highRiskKey, $highRiskAccounts, 86400);
    }
}
