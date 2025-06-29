<?php

namespace App\Domain\Fraud\Services;

use App\Models\FraudRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RuleEngineService
{
    /**
     * Evaluate all active rules against context
     */
    public function evaluate(array $context): array
    {
        $rules = $this->getActiveRules();
        $results = [
            'total_score' => 0,
            'triggered_rules' => [],
            'blocking_rules' => [],
            'rule_scores' => [],
            'rule_details' => [],
        ];
        
        foreach ($rules as $rule) {
            try {
                if ($this->evaluateRule($rule, $context)) {
                    // Rule triggered
                    $score = $rule->calculateScore($context);
                    
                    $results['triggered_rules'][] = $rule->code;
                    $results['rule_scores'][$rule->code] = $score;
                    $results['total_score'] += $score;
                    
                    $results['rule_details'][$rule->code] = [
                        'name' => $rule->name,
                        'category' => $rule->category,
                        'severity' => $rule->severity,
                        'score' => $score,
                        'actions' => $rule->actions,
                    ];
                    
                    // Check if blocking
                    if ($rule->is_blocking) {
                        $results['blocking_rules'][] = $rule->code;
                    }
                    
                    // Record trigger
                    $rule->recordTrigger();
                    
                    // Execute rule actions
                    $this->executeRuleActions($rule, $context);
                }
            } catch (\Exception $e) {
                Log::error('Rule evaluation failed', [
                    'rule_code' => $rule->code,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Cap total score at 100
        $results['total_score'] = min(100, $results['total_score']);
        
        return $results;
    }
    
    /**
     * Get active fraud rules
     */
    protected function getActiveRules(): Collection
    {
        return Cache::remember('active_fraud_rules', 300, function () {
            return FraudRule::where('is_active', true)
                ->orderBy('severity', 'desc')
                ->orderBy('base_score', 'desc')
                ->get();
        });
    }
    
    /**
     * Evaluate a single rule
     */
    protected function evaluateRule(FraudRule $rule, array $context): bool
    {
        // Check category-specific evaluation
        switch ($rule->category) {
            case FraudRule::CATEGORY_VELOCITY:
                return $this->evaluateVelocityRule($rule, $context);
                
            case FraudRule::CATEGORY_PATTERN:
                return $this->evaluatePatternRule($rule, $context);
                
            case FraudRule::CATEGORY_AMOUNT:
                return $this->evaluateAmountRule($rule, $context);
                
            case FraudRule::CATEGORY_GEOGRAPHY:
                return $this->evaluateGeographyRule($rule, $context);
                
            case FraudRule::CATEGORY_DEVICE:
                return $this->evaluateDeviceRule($rule, $context);
                
            case FraudRule::CATEGORY_BEHAVIOR:
                return $this->evaluateBehaviorRule($rule, $context);
                
            default:
                // Generic evaluation
                return $rule->evaluate($context);
        }
    }
    
    /**
     * Evaluate velocity rule
     */
    protected function evaluateVelocityRule(FraudRule $rule, array $context): bool
    {
        $thresholds = $rule->thresholds ?? [];
        
        // Check transaction count velocity
        if (isset($thresholds['max_daily_transactions']) && 
            ($context['daily_transaction_count'] ?? 0) > $thresholds['max_daily_transactions']) {
            return true;
        }
        
        // Check transaction volume velocity
        if (isset($thresholds['max_daily_volume']) && 
            ($context['daily_transaction_volume'] ?? 0) > $thresholds['max_daily_volume']) {
            return true;
        }
        
        // Check hourly velocity
        if (isset($thresholds['max_hourly_transactions']) && 
            ($context['hourly_transaction_count'] ?? 0) > $thresholds['max_hourly_transactions']) {
            return true;
        }
        
        // Time-based velocity
        if ($rule->time_window && isset($thresholds['max_transactions_in_window'])) {
            $count = $this->getTransactionCountInWindow($context['user']['id'] ?? null, $rule->time_window);
            if ($count > $thresholds['max_transactions_in_window']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Evaluate pattern rule
     */
    protected function evaluatePatternRule(FraudRule $rule, array $context): bool
    {
        $conditions = $rule->conditions;
        
        foreach ($conditions as $condition) {
            $patternType = $condition['pattern'] ?? null;
            
            switch ($patternType) {
                case 'rapid_succession':
                    if ($this->detectRapidSuccession($context)) {
                        return true;
                    }
                    break;
                    
                case 'round_amounts':
                    if ($this->detectRoundAmounts($context)) {
                        return true;
                    }
                    break;
                    
                case 'splitting':
                    if ($this->detectSplitting($context)) {
                        return true;
                    }
                    break;
                    
                case 'unusual_sequence':
                    if ($this->detectUnusualSequence($context)) {
                        return true;
                    }
                    break;
            }
        }
        
        return false;
    }
    
    /**
     * Evaluate amount rule
     */
    protected function evaluateAmountRule(FraudRule $rule, array $context): bool
    {
        $amount = $context['amount'] ?? 0;
        $thresholds = $rule->thresholds ?? [];
        
        // Check absolute amount
        if (isset($thresholds['max_amount']) && $amount > $thresholds['max_amount']) {
            return true;
        }
        
        if (isset($thresholds['min_amount']) && $amount < $thresholds['min_amount']) {
            return true;
        }
        
        // Check relative to account balance
        if (isset($thresholds['max_percentage_of_balance'])) {
            $balance = $context['account_balance'] ?? 0;
            if ($balance > 0 && ($amount / $balance) > ($thresholds['max_percentage_of_balance'] / 100)) {
                return true;
            }
        }
        
        // Check relative to historical average
        if (isset($thresholds['max_multiple_of_average'])) {
            $avgAmount = $context['user']['avg_transaction_amount'] ?? 0;
            if ($avgAmount > 0 && $amount > ($avgAmount * $thresholds['max_multiple_of_average'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Evaluate geography rule
     */
    protected function evaluateGeographyRule(FraudRule $rule, array $context): bool
    {
        $conditions = $rule->conditions;
        
        // Check high-risk countries
        if (isset($conditions['high_risk_countries'])) {
            $country = $context['ip_country'] ?? $context['transaction']['destination_country'] ?? null;
            if ($country && in_array($country, $conditions['high_risk_countries'])) {
                return true;
            }
        }
        
        // Check country mismatch
        if (isset($conditions['check_country_mismatch']) && $conditions['check_country_mismatch']) {
            $userCountry = $context['user']['country'] ?? null;
            $txCountry = $context['ip_country'] ?? null;
            if ($userCountry && $txCountry && $userCountry !== $txCountry) {
                return true;
            }
        }
        
        // Check impossible travel
        if (isset($conditions['check_impossible_travel']) && $conditions['check_impossible_travel']) {
            if ($this->detectImpossibleTravel($context)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Evaluate device rule
     */
    protected function evaluateDeviceRule(FraudRule $rule, array $context): bool
    {
        $deviceData = $context['device_data'] ?? [];
        $conditions = $rule->conditions;
        
        // Check for VPN/Proxy/Tor
        if (isset($conditions['block_vpn']) && $conditions['block_vpn'] && ($deviceData['is_vpn'] ?? false)) {
            return true;
        }
        
        if (isset($conditions['block_proxy']) && $conditions['block_proxy'] && ($deviceData['is_proxy'] ?? false)) {
            return true;
        }
        
        if (isset($conditions['block_tor']) && $conditions['block_tor'] && ($deviceData['is_tor'] ?? false)) {
            return true;
        }
        
        // Check device trust
        if (isset($conditions['require_trusted_device']) && $conditions['require_trusted_device']) {
            $deviceId = $deviceData['fingerprint_id'] ?? null;
            if (!$deviceId || !$this->isDeviceTrusted($deviceId)) {
                return true;
            }
        }
        
        // Check new device
        if (isset($conditions['flag_new_device']) && $conditions['flag_new_device']) {
            $deviceId = $deviceData['fingerprint_id'] ?? null;
            if ($deviceId && $this->isNewDevice($deviceId)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Evaluate behavior rule
     */
    protected function evaluateBehaviorRule(FraudRule $rule, array $context): bool
    {
        $conditions = $rule->conditions;
        
        // Check for abnormal behavior
        if (isset($conditions['check_abnormal_behavior']) && $conditions['check_abnormal_behavior']) {
            $behaviorScore = $context['behavioral_analysis']['deviation_score'] ?? 0;
            $threshold = $conditions['abnormal_threshold'] ?? 70;
            
            if ($behaviorScore > $threshold) {
                return true;
            }
        }
        
        // Check for specific behavioral patterns
        if (isset($conditions['behavioral_patterns'])) {
            foreach ($conditions['behavioral_patterns'] as $pattern) {
                if ($this->detectBehavioralPattern($pattern, $context)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Execute rule actions
     */
    protected function executeRuleActions(FraudRule $rule, array $context): void
    {
        foreach ($rule->actions as $action) {
            switch ($action) {
                case FraudRule::ACTION_NOTIFY:
                    $this->sendNotification($rule, $context);
                    break;
                    
                case FraudRule::ACTION_FLAG:
                    $this->flagTransaction($rule, $context);
                    break;
                    
                // Other actions handled by main fraud detection service
            }
        }
    }
    
    /**
     * Detect rapid succession pattern
     */
    protected function detectRapidSuccession(array $context): bool
    {
        $timeSinceLast = $context['time_since_last_transaction'] ?? null;
        
        // Less than 2 minutes since last transaction
        return $timeSinceLast !== null && $timeSinceLast < 2;
    }
    
    /**
     * Detect round amounts pattern
     */
    protected function detectRoundAmounts(array $context): bool
    {
        $amount = $context['amount'] ?? 0;
        
        // Check if amount is round (divisible by 100 or 1000)
        return $amount >= 100 && ($amount % 100 == 0 || $amount % 1000 == 0);
    }
    
    /**
     * Detect transaction splitting pattern
     */
    protected function detectSplitting(array $context): bool
    {
        // Check if multiple transactions just below a threshold
        $amount = $context['amount'] ?? 0;
        $dailyCount = $context['daily_transaction_count'] ?? 0;
        
        // Common thresholds to avoid
        $thresholds = [10000, 5000, 3000];
        
        foreach ($thresholds as $threshold) {
            if ($amount > ($threshold * 0.9) && $amount < $threshold && $dailyCount > 2) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detect unusual transaction sequence
     */
    protected function detectUnusualSequence(array $context): bool
    {
        // Example: deposit followed immediately by withdrawal
        $transactionType = $context['type'] ?? '';
        $timeSinceLast = $context['time_since_last_transaction'] ?? null;
        $lastTransactionType = $context['last_transaction_type'] ?? null;
        
        if ($transactionType === 'withdrawal' && 
            $lastTransactionType === 'deposit' && 
            $timeSinceLast !== null && 
            $timeSinceLast < 10) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Detect impossible travel
     */
    protected function detectImpossibleTravel(array $context): bool
    {
        $currentLocation = [
            'country' => $context['ip_country'] ?? null,
            'city' => $context['ip_city'] ?? null,
            'timestamp' => $context['timestamp'] ?? now(),
        ];
        
        $lastLocation = $context['last_location'] ?? null;
        
        if (!$lastLocation || !$currentLocation['country']) {
            return false;
        }
        
        // Different country within short time
        if ($currentLocation['country'] !== $lastLocation['country']) {
            $timeDiff = $currentLocation['timestamp']->diffInHours($lastLocation['timestamp']);
            
            // Less than 2 hours between different countries (impossible travel)
            if ($timeDiff < 2) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get transaction count in time window
     */
    protected function getTransactionCountInWindow(?int $userId, string $timeWindow): int
    {
        if (!$userId) {
            return 0;
        }
        
        $minutes = match($timeWindow) {
            '1h' => 60,
            '24h' => 1440,
            '7d' => 10080,
            '30d' => 43200,
            default => 60,
        };
        
        return Cache::remember(
            "txn_count_{$userId}_{$timeWindow}",
            60,
            function () use ($userId, $minutes) {
                return \App\Models\Transaction::whereHas('account', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->where('created_at', '>=', now()->subMinutes($minutes))
                ->count();
            }
        );
    }
    
    /**
     * Check if device is trusted
     */
    protected function isDeviceTrusted(string $deviceId): bool
    {
        $device = \App\Models\DeviceFingerprint::find($deviceId);
        return $device && $device->isTrusted();
    }
    
    /**
     * Check if device is new
     */
    protected function isNewDevice(string $deviceId): bool
    {
        $device = \App\Models\DeviceFingerprint::find($deviceId);
        return $device && $device->isNew();
    }
    
    /**
     * Detect behavioral pattern
     */
    protected function detectBehavioralPattern(string $pattern, array $context): bool
    {
        // Implement specific behavioral pattern detection
        return false;
    }
    
    /**
     * Send notification for rule
     */
    protected function sendNotification(FraudRule $rule, array $context): void
    {
        // Implement notification logic
        Log::info('Fraud rule triggered notification', [
            'rule_code' => $rule->code,
            'transaction_id' => $context['transaction']['id'] ?? null,
        ]);
    }
    
    /**
     * Flag transaction
     */
    protected function flagTransaction(FraudRule $rule, array $context): void
    {
        // Implement flagging logic
        Log::info('Transaction flagged by rule', [
            'rule_code' => $rule->code,
            'transaction_id' => $context['transaction']['id'] ?? null,
        ]);
    }
    
    /**
     * Create default fraud rules
     */
    public function createDefaultRules(): void
    {
        $defaultRules = [
            [
                'name' => 'High Daily Transaction Volume',
                'category' => FraudRule::CATEGORY_VELOCITY,
                'severity' => FraudRule::SEVERITY_HIGH,
                'thresholds' => ['max_daily_volume' => 50000],
                'base_score' => 60,
            ],
            [
                'name' => 'Rapid Transactions',
                'category' => FraudRule::CATEGORY_PATTERN,
                'severity' => FraudRule::SEVERITY_MEDIUM,
                'conditions' => [['pattern' => 'rapid_succession']],
                'base_score' => 40,
            ],
            [
                'name' => 'Large Transaction Amount',
                'category' => FraudRule::CATEGORY_AMOUNT,
                'severity' => FraudRule::SEVERITY_HIGH,
                'thresholds' => ['max_amount' => 25000],
                'base_score' => 50,
            ],
            [
                'name' => 'High Risk Country',
                'category' => FraudRule::CATEGORY_GEOGRAPHY,
                'severity' => FraudRule::SEVERITY_HIGH,
                'conditions' => ['high_risk_countries' => ['NG', 'PK', 'ID']],
                'base_score' => 45,
            ],
            [
                'name' => 'VPN/Proxy Detection',
                'category' => FraudRule::CATEGORY_DEVICE,
                'severity' => FraudRule::SEVERITY_MEDIUM,
                'conditions' => ['block_vpn' => true, 'block_proxy' => true],
                'base_score' => 35,
            ],
        ];
        
        foreach ($defaultRules as $ruleData) {
            FraudRule::firstOrCreate(
                ['name' => $ruleData['name']],
                array_merge($ruleData, [
                    'description' => "Default rule: {$ruleData['name']}",
                    'actions' => [FraudRule::ACTION_FLAG, FraudRule::ACTION_NOTIFY],
                ])
            );
        }
    }
}