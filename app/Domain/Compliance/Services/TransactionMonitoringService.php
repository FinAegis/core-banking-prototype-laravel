<?php

namespace App\Domain\Compliance\Services;

use App\Models\Transaction;
use App\Models\TransactionMonitoringRule;
use App\Models\SuspiciousActivityReport;
use App\Models\CustomerRiskProfile;
use App\Domain\Compliance\Events\SuspiciousActivityDetected;
use App\Domain\Compliance\Events\TransactionBlocked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

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
     * Monitor transaction in real-time
     */
    public function monitorTransaction(Transaction $transaction): array
    {
        $alerts = [];
        $actions = [];
        
        try {
            // Get customer risk profile
            $riskProfile = $this->getCustomerRiskProfile($transaction);
            
            // Get applicable rules
            $rules = $this->getApplicableRules($transaction, $riskProfile);
            
            // Evaluate each rule
            foreach ($rules as $rule) {
                if ($this->evaluateRule($rule, $transaction, $riskProfile)) {
                    $alerts[] = $this->createAlert($rule, $transaction);
                    $actions = array_merge($actions, $rule->getActions());
                    
                    // Record rule trigger
                    $rule->recordTrigger();
                }
            }
            
            // Process actions
            $this->processActions($actions, $transaction, $alerts);
            
            // Update behavioral risk if patterns detected
            if (!empty($alerts)) {
                $this->updateBehavioralRisk($transaction, $alerts);
            }
            
            return [
                'passed' => empty($alerts) || !in_array(TransactionMonitoringRule::ACTION_BLOCK, $actions),
                'alerts' => $alerts,
                'actions' => array_unique($actions),
            ];
            
        } catch (\Exception $e) {
            Log::error('Transaction monitoring failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            
            // Fail-safe: allow transaction but flag for review
            return [
                'passed' => true,
                'alerts' => [[
                    'type' => 'system_error',
                    'message' => 'Monitoring system error - flagged for manual review',
                ]],
                'actions' => [TransactionMonitoringRule::ACTION_REVIEW],
            ];
        }
    }
    
    /**
     * Batch monitor transactions
     */
    public function batchMonitor(Collection $transactions): array
    {
        $results = [];
        
        foreach ($transactions as $transaction) {
            $results[$transaction->id] = $this->monitorTransaction($transaction);
        }
        
        // Look for patterns across batch
        $patterns = $this->detectBatchPatterns($transactions, $results);
        
        if (!empty($patterns)) {
            $this->handleDetectedPatterns($patterns, $transactions);
        }
        
        return $results;
    }
    
    /**
     * Get customer risk profile
     */
    protected function getCustomerRiskProfile(Transaction $transaction): ?CustomerRiskProfile
    {
        $account = $transaction->account;
        if (!$account || !$account->user_id) {
            return null;
        }
        
        return CustomerRiskProfile::where('user_id', $account->user_id)->first();
    }
    
    /**
     * Get applicable monitoring rules
     */
    protected function getApplicableRules(Transaction $transaction, ?CustomerRiskProfile $riskProfile): Collection
    {
        $query = TransactionMonitoringRule::where('is_active', true);
        
        // Filter by customer type and risk level if profile exists
        if ($riskProfile) {
            $customerType = $riskProfile->business_type ? 'business' : 'individual';
            $riskLevel = $riskProfile->risk_rating;
            
            $query->where(function ($q) use ($customerType, $riskLevel) {
                $q->whereNull('applies_to_customer_types')
                  ->orWhereJsonContains('applies_to_customer_types', $customerType);
            })->where(function ($q) use ($riskLevel) {
                $q->whereNull('applies_to_risk_levels')
                  ->orWhereJsonContains('applies_to_risk_levels', $riskLevel);
            });
        }
        
        // Filter by transaction type
        $query->where(function ($q) use ($transaction) {
            $q->whereNull('applies_to_transaction_types')
              ->orWhereJsonContains('applies_to_transaction_types', $transaction->type);
        });
        
        return $query->get();
    }
    
    /**
     * Evaluate monitoring rule
     */
    protected function evaluateRule(
        TransactionMonitoringRule $rule,
        Transaction $transaction,
        ?CustomerRiskProfile $riskProfile
    ): bool {
        switch ($rule->category) {
            case TransactionMonitoringRule::CATEGORY_VELOCITY:
                return $this->evaluateVelocityRule($rule, $transaction);
                
            case TransactionMonitoringRule::CATEGORY_PATTERN:
                return $this->evaluatePatternRule($rule, $transaction);
                
            case TransactionMonitoringRule::CATEGORY_THRESHOLD:
                return $this->evaluateThresholdRule($rule, $transaction);
                
            case TransactionMonitoringRule::CATEGORY_GEOGRAPHY:
                return $this->evaluateGeographyRule($rule, $transaction);
                
            case TransactionMonitoringRule::CATEGORY_BEHAVIOR:
                return $this->evaluateBehaviorRule($rule, $transaction, $riskProfile);
                
            default:
                return false;
        }
    }
    
    /**
     * Evaluate velocity rule
     */
    protected function evaluateVelocityRule(TransactionMonitoringRule $rule, Transaction $transaction): bool
    {
        $timeWindow = $rule->time_window ?? '24h';
        $thresholdCount = $rule->threshold_count ?? PHP_INT_MAX;
        $thresholdAmount = $rule->threshold_amount ?? PHP_FLOAT_MAX;
        
        // Parse time window
        $startTime = match($timeWindow) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };
        
        // Count recent transactions
        $recentStats = Transaction::where('account_id', $transaction->account_id)
            ->where('created_at', '>=', $startTime)
            ->selectRaw('COUNT(*) as count, SUM(amount) as total')
            ->first();
        
        return $recentStats->count > $thresholdCount || 
               $recentStats->total > $thresholdAmount;
    }
    
    /**
     * Evaluate pattern rule
     */
    protected function evaluatePatternRule(TransactionMonitoringRule $rule, Transaction $transaction): bool
    {
        $conditions = $rule->conditions;
        
        // Check for structuring pattern
        if (isset($conditions['detect_structuring']) && $conditions['detect_structuring']) {
            return $this->detectStructuring($transaction);
        }
        
        // Check for rapid fund movement
        if (isset($conditions['detect_rapid_movement']) && $conditions['detect_rapid_movement']) {
            return $this->detectRapidMovement($transaction);
        }
        
        // Check for round amounts
        if (isset($conditions['detect_round_amounts']) && $conditions['detect_round_amounts']) {
            return $this->detectRoundAmounts($transaction);
        }
        
        return false;
    }
    
    /**
     * Evaluate threshold rule
     */
    protected function evaluateThresholdRule(TransactionMonitoringRule $rule, Transaction $transaction): bool
    {
        $threshold = $rule->threshold_amount ?? 0;
        
        // Check transaction amount
        if ($transaction->amount >= $threshold) {
            return true;
        }
        
        // Check cumulative amount if specified
        if (isset($rule->parameters['check_cumulative']) && $rule->parameters['check_cumulative']) {
            $timeWindow = $rule->time_window ?? '24h';
            $startTime = $this->parseTimeWindow($timeWindow);
            
            $cumulative = Transaction::where('account_id', $transaction->account_id)
                ->where('created_at', '>=', $startTime)
                ->sum('amount');
            
            return $cumulative >= $threshold;
        }
        
        return false;
    }
    
    /**
     * Evaluate geography rule
     */
    protected function evaluateGeographyRule(TransactionMonitoringRule $rule, Transaction $transaction): bool
    {
        $highRiskCountries = $rule->applies_to_countries ?? [];
        
        // Check transaction metadata for country information
        $metadata = $transaction->metadata ?? [];
        $originCountry = $metadata['origin_country'] ?? null;
        $destinationCountry = $metadata['destination_country'] ?? null;
        
        if ($originCountry && in_array($originCountry, $highRiskCountries)) {
            return true;
        }
        
        if ($destinationCountry && in_array($destinationCountry, $highRiskCountries)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Evaluate behavior rule
     */
    protected function evaluateBehaviorRule(
        TransactionMonitoringRule $rule,
        Transaction $transaction,
        ?CustomerRiskProfile $riskProfile
    ): bool {
        if (!$riskProfile) {
            return false;
        }
        
        // Check for deviation from normal behavior
        $behavioralRisk = $riskProfile->behavioral_risk ?? [];
        $patterns = $behavioralRisk['patterns'] ?? [];
        
        // Check if transaction deviates from established patterns
        $conditions = $rule->conditions;
        
        foreach ($conditions as $condition) {
            if ($this->checkBehavioralDeviation($condition, $transaction, $behavioralRisk)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect structuring pattern
     */
    protected function detectStructuring(Transaction $transaction): bool
    {
        // Look for multiple transactions just below reporting threshold
        $reportingThreshold = 10000; // CTR threshold
        $marginPercentage = 0.1; // 10% margin
        
        $lowerBound = $reportingThreshold * (1 - $marginPercentage);
        
        if ($transaction->amount >= $lowerBound && $transaction->amount < $reportingThreshold) {
            // Check for similar transactions in past 24 hours
            $similarCount = Transaction::where('account_id', $transaction->account_id)
                ->where('created_at', '>=', now()->subDay())
                ->whereBetween('amount', [$lowerBound, $reportingThreshold])
                ->count();
            
            return $similarCount >= 3;
        }
        
        return false;
    }
    
    /**
     * Detect rapid fund movement
     */
    protected function detectRapidMovement(Transaction $transaction): bool
    {
        if ($transaction->type !== 'withdrawal') {
            return false;
        }
        
        // Check if funds were recently deposited
        $deposit = Transaction::where('account_id', $transaction->account_id)
            ->where('type', 'deposit')
            ->where('amount', '>=', $transaction->amount * 0.9) // 90% of withdrawal amount
            ->where('created_at', '>=', now()->subHours(24))
            ->first();
        
        return $deposit !== null;
    }
    
    /**
     * Detect round amounts pattern
     */
    protected function detectRoundAmounts(Transaction $transaction): bool
    {
        $amount = $transaction->amount;
        
        // Check if amount is round (divisible by 100, 500, or 1000)
        $roundDivisors = [1000, 500, 100];
        
        foreach ($roundDivisors as $divisor) {
            if ($amount >= $divisor && $amount % $divisor === 0) {
                // Check frequency of round amounts
                $roundCount = Transaction::where('account_id', $transaction->account_id)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->whereRaw('amount % ? = 0', [$divisor])
                    ->count();
                
                return $roundCount >= 5;
            }
        }
        
        return false;
    }
    
    /**
     * Create alert from rule and transaction
     */
    protected function createAlert(TransactionMonitoringRule $rule, Transaction $transaction): array
    {
        return [
            'rule_id' => $rule->id,
            'rule_code' => $rule->rule_code,
            'rule_name' => $rule->name,
            'category' => $rule->category,
            'risk_level' => $rule->risk_level,
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
            'timestamp' => now()->toIso8601String(),
            'description' => $this->generateAlertDescription($rule, $transaction),
        ];
    }
    
    /**
     * Generate alert description
     */
    protected function generateAlertDescription(TransactionMonitoringRule $rule, Transaction $transaction): string
    {
        $amount = number_format($transaction->amount, 2);
        $currency = $transaction->currency;
        
        return match($rule->category) {
            TransactionMonitoringRule::CATEGORY_VELOCITY => 
                "High velocity detected: Multiple transactions totaling {$currency} {$amount}",
            TransactionMonitoringRule::CATEGORY_PATTERN => 
                "Suspicious pattern detected: {$rule->name}",
            TransactionMonitoringRule::CATEGORY_THRESHOLD => 
                "Threshold exceeded: Transaction of {$currency} {$amount}",
            TransactionMonitoringRule::CATEGORY_GEOGRAPHY => 
                "High-risk geography: Transaction involving restricted country",
            TransactionMonitoringRule::CATEGORY_BEHAVIOR => 
                "Behavioral anomaly: Deviation from established pattern",
            default => "Alert: {$rule->name}",
        };
    }
    
    /**
     * Process monitoring actions
     */
    protected function processActions(array $actions, Transaction $transaction, array $alerts): void
    {
        foreach ($actions as $action) {
            switch ($action) {
                case TransactionMonitoringRule::ACTION_BLOCK:
                    $this->blockTransaction($transaction, $alerts);
                    break;
                    
                case TransactionMonitoringRule::ACTION_ALERT:
                    $this->sendAlert($transaction, $alerts);
                    break;
                    
                case TransactionMonitoringRule::ACTION_REVIEW:
                    $this->flagForReview($transaction, $alerts);
                    break;
                    
                case TransactionMonitoringRule::ACTION_REPORT:
                    $this->createSAR($transaction, $alerts);
                    break;
            }
        }
    }
    
    /**
     * Block transaction
     */
    protected function blockTransaction(Transaction $transaction, array $alerts): void
    {
        $transaction->update([
            'status' => 'blocked',
            'metadata' => array_merge($transaction->metadata ?? [], [
                'blocked_at' => now()->toIso8601String(),
                'block_reason' => 'AML monitoring alert',
                'alerts' => $alerts,
            ]),
        ]);
        
        event(new TransactionBlocked($transaction, $alerts));
    }
    
    /**
     * Send alert
     */
    protected function sendAlert(Transaction $transaction, array $alerts): void
    {
        event(new SuspiciousActivityDetected($transaction, $alerts));
    }
    
    /**
     * Flag transaction for review
     */
    protected function flagForReview(Transaction $transaction, array $alerts): void
    {
        $transaction->update([
            'metadata' => array_merge($transaction->metadata ?? [], [
                'requires_review' => true,
                'review_requested_at' => now()->toIso8601String(),
                'review_alerts' => $alerts,
            ]),
        ]);
    }
    
    /**
     * Create Suspicious Activity Report
     */
    protected function createSAR(Transaction $transaction, array $alerts): void
    {
        $highRiskAlerts = array_filter($alerts, fn($alert) => 
            $alert['risk_level'] === TransactionMonitoringRule::RISK_LEVEL_HIGH
        );
        
        if (!empty($highRiskAlerts)) {
            $this->sarService->createFromTransaction($transaction, $alerts);
        }
    }
    
    /**
     * Update behavioral risk based on alerts
     */
    protected function updateBehavioralRisk(Transaction $transaction, array $alerts): void
    {
        $riskProfile = $this->getCustomerRiskProfile($transaction);
        if (!$riskProfile) {
            return;
        }
        
        $behavioralRisk = $riskProfile->behavioral_risk ?? [];
        $patterns = $behavioralRisk['patterns'] ?? [];
        
        // Add detected patterns
        foreach ($alerts as $alert) {
            if ($alert['category'] === TransactionMonitoringRule::CATEGORY_PATTERN) {
                $patterns[] = $alert['rule_code'];
            }
        }
        
        $behavioralRisk['patterns'] = array_unique($patterns);
        $behavioralRisk['last_alert'] = now()->toIso8601String();
        $behavioralRisk['alert_count'] = ($behavioralRisk['alert_count'] ?? 0) + count($alerts);
        
        $riskProfile->update([
            'behavioral_risk' => $behavioralRisk,
            'suspicious_activities_count' => $riskProfile->suspicious_activities_count + 1,
            'last_suspicious_activity_at' => now(),
        ]);
        
        // Trigger risk reassessment if multiple alerts
        if (count($alerts) >= 3) {
            $riskProfile->updateRiskAssessment();
        }
    }
    
    /**
     * Detect patterns across batch of transactions
     */
    protected function detectBatchPatterns(Collection $transactions, array $results): array
    {
        $patterns = [];
        
        // Group by account
        $byAccount = $transactions->groupBy('account_id');
        
        foreach ($byAccount as $accountId => $accountTransactions) {
            // Check for smurfing (multiple small transactions)
            if ($this->detectSmurfing($accountTransactions)) {
                $patterns[] = [
                    'type' => 'smurfing',
                    'account_id' => $accountId,
                    'transactions' => $accountTransactions->pluck('id')->toArray(),
                ];
            }
            
            // Check for layering
            if ($this->detectLayering($accountTransactions)) {
                $patterns[] = [
                    'type' => 'layering',
                    'account_id' => $accountId,
                    'transactions' => $accountTransactions->pluck('id')->toArray(),
                ];
            }
        }
        
        return $patterns;
    }
    
    /**
     * Detect smurfing pattern
     */
    protected function detectSmurfing(Collection $transactions): bool
    {
        if ($transactions->count() < 5) {
            return false;
        }
        
        $totalAmount = $transactions->sum('amount');
        $avgAmount = $totalAmount / $transactions->count();
        
        // Check if transactions are suspiciously similar and below threshold
        $threshold = 3000; // Smurfing threshold
        $variance = $transactions->pluck('amount')->variance();
        
        return $avgAmount < $threshold && $variance < ($avgAmount * 0.1);
    }
    
    /**
     * Detect layering pattern
     */
    protected function detectLayering(Collection $transactions): bool
    {
        // Look for rapid in-and-out pattern
        $deposits = $transactions->where('type', 'deposit');
        $withdrawals = $transactions->where('type', 'withdrawal');
        
        if ($deposits->isEmpty() || $withdrawals->isEmpty()) {
            return false;
        }
        
        // Check if deposits and withdrawals are closely matched
        $depositTotal = $deposits->sum('amount');
        $withdrawalTotal = $withdrawals->sum('amount');
        
        $difference = abs($depositTotal - $withdrawalTotal);
        $percentDiff = $difference / max($depositTotal, $withdrawalTotal);
        
        return $percentDiff < 0.05 && $transactions->count() >= 6;
    }
    
    /**
     * Handle detected patterns
     */
    protected function handleDetectedPatterns(array $patterns, Collection $transactions): void
    {
        foreach ($patterns as $pattern) {
            // Create SAR for pattern
            $this->sarService->createFromPattern($pattern, $transactions);
            
            // Update risk profiles
            $accountIds = $transactions->pluck('account_id')->unique();
            foreach ($accountIds as $accountId) {
                $this->riskService->escalateRiskForAccount($accountId, $pattern['type']);
            }
        }
    }
    
    /**
     * Parse time window string
     */
    protected function parseTimeWindow(string $window): \Carbon\Carbon
    {
        return match($window) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };
    }
    
    /**
     * Check behavioral deviation
     */
    protected function checkBehavioralDeviation(array $condition, Transaction $transaction, array $behavioralRisk): bool
    {
        $type = $condition['type'] ?? null;
        
        return match($type) {
            'unusual_amount' => $this->isUnusualAmount($transaction, $behavioralRisk),
            'unusual_time' => $this->isUnusualTime($transaction, $behavioralRisk),
            'unusual_frequency' => $this->isUnusualFrequency($transaction, $behavioralRisk),
            'unusual_destination' => $this->isUnusualDestination($transaction, $behavioralRisk),
            default => false,
        };
    }
    
    /**
     * Check if transaction amount is unusual
     */
    protected function isUnusualAmount(Transaction $transaction, array $behavioralRisk): bool
    {
        $avgAmount = $behavioralRisk['avg_transaction_amount'] ?? 0;
        $stdDev = $behavioralRisk['transaction_amount_std_dev'] ?? 0;
        
        if ($avgAmount === 0 || $stdDev === 0) {
            return false;
        }
        
        // Check if amount is more than 3 standard deviations from mean
        $deviation = abs($transaction->amount - $avgAmount) / $stdDev;
        
        return $deviation > 3;
    }
    
    /**
     * Check if transaction time is unusual
     */
    protected function isUnusualTime(Transaction $transaction, array $behavioralRisk): bool
    {
        $usualHours = $behavioralRisk['usual_transaction_hours'] ?? [];
        
        if (empty($usualHours)) {
            return false;
        }
        
        $hour = $transaction->created_at->hour;
        
        return !in_array($hour, $usualHours);
    }
    
    /**
     * Check if transaction frequency is unusual
     */
    protected function isUnusualFrequency(Transaction $transaction, array $behavioralRisk): bool
    {
        $avgDaily = $behavioralRisk['avg_daily_transactions'] ?? 0;
        
        if ($avgDaily === 0) {
            return false;
        }
        
        $todayCount = Transaction::where('account_id', $transaction->account_id)
            ->whereDate('created_at', today())
            ->count();
        
        return $todayCount > ($avgDaily * 3); // 3x normal frequency
    }
    
    /**
     * Check if transaction destination is unusual
     */
    protected function isUnusualDestination(Transaction $transaction, array $behavioralRisk): bool
    {
        $knownDestinations = $behavioralRisk['known_destinations'] ?? [];
        $metadata = $transaction->metadata ?? [];
        $destination = $metadata['destination_account'] ?? $metadata['destination_country'] ?? null;
        
        if (!$destination || empty($knownDestinations)) {
            return false;
        }
        
        return !in_array($destination, $knownDestinations);
    }
}