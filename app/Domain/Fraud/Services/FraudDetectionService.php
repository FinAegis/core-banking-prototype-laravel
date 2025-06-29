<?php

namespace App\Domain\Fraud\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\FraudScore;
use App\Models\FraudRule;
use App\Models\DeviceFingerprint;
use App\Models\BehavioralProfile;
use App\Domain\Fraud\Events\FraudDetected;
use App\Domain\Fraud\Events\TransactionBlocked;
use App\Domain\Fraud\Events\ChallengeRequired;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FraudDetectionService
{
    private RuleEngineService $ruleEngine;
    private BehavioralAnalysisService $behavioralAnalysis;
    private DeviceFingerprintService $deviceService;
    private MachineLearningService $mlService;
    private FraudCaseService $caseService;
    
    public function __construct(
        RuleEngineService $ruleEngine,
        BehavioralAnalysisService $behavioralAnalysis,
        DeviceFingerprintService $deviceService,
        MachineLearningService $mlService,
        FraudCaseService $caseService
    ) {
        $this->ruleEngine = $ruleEngine;
        $this->behavioralAnalysis = $behavioralAnalysis;
        $this->deviceService = $deviceService;
        $this->mlService = $mlService;
        $this->caseService = $caseService;
    }
    
    /**
     * Analyze transaction for fraud in real-time
     */
    public function analyzeTransaction(Transaction $transaction, array $context = []): FraudScore
    {
        return DB::transaction(function () use ($transaction, $context) {
            // Get user and account
            $account = $transaction->account;
            $user = $account->user;
            
            // Prepare analysis context
            $analysisContext = $this->prepareContext($transaction, $user, $context);
            
            // Create fraud score record
            $fraudScore = FraudScore::create([
                'entity_id' => $transaction->id,
                'entity_type' => Transaction::class,
                'score_type' => FraudScore::SCORE_TYPE_REAL_TIME,
                'entity_snapshot' => $this->createEntitySnapshot($transaction),
            ]);
            
            try {
                // 1. Rule-based analysis
                $ruleResults = $this->ruleEngine->evaluate($analysisContext);
                
                // 2. Behavioral analysis
                $behavioralResults = $this->behavioralAnalysis->analyze($user, $transaction, $analysisContext);
                
                // 3. Device analysis
                $deviceResults = $this->deviceService->analyzeDevice($analysisContext['device_data'] ?? []);
                
                // 4. ML prediction (if enabled)
                $mlResults = null;
                if ($this->mlService->isEnabled()) {
                    $mlResults = $this->mlService->predict($analysisContext);
                }
                
                // 5. Calculate final score
                $totalScore = $this->calculateTotalScore(
                    $ruleResults,
                    $behavioralResults,
                    $deviceResults,
                    $mlResults
                );
                
                // 6. Determine risk level and decision
                $riskLevel = FraudScore::calculateRiskLevel($totalScore);
                $decision = $this->makeDecision($totalScore, $riskLevel, $ruleResults);
                
                // 7. Update fraud score
                $fraudScore->update([
                    'total_score' => $totalScore,
                    'risk_level' => $riskLevel,
                    'score_breakdown' => $this->createScoreBreakdown($ruleResults, $behavioralResults, $deviceResults, $mlResults),
                    'triggered_rules' => $ruleResults['triggered_rules'] ?? [],
                    'behavioral_factors' => $behavioralResults,
                    'device_factors' => $deviceResults,
                    'network_factors' => $this->extractNetworkFactors($analysisContext),
                    'ml_score' => $mlResults['score'] ?? null,
                    'ml_model_version' => $mlResults['model_version'] ?? null,
                    'ml_features' => $mlResults['features'] ?? null,
                    'ml_explanation' => $mlResults['explanation'] ?? null,
                    'decision' => $decision,
                    'decision_factors' => $this->extractDecisionFactors($totalScore, $ruleResults),
                    'decision_at' => now(),
                ]);
                
                // 8. Take action based on decision
                $this->executeDecision($transaction, $fraudScore, $decision);
                
                // 9. Update behavioral profile
                $this->behavioralAnalysis->updateProfile($user, $transaction, $fraudScore);
                
                return $fraudScore;
                
            } catch (\Exception $e) {
                Log::error('Fraud detection failed', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
                
                // Fail-safe: allow transaction but flag for review
                $fraudScore->update([
                    'total_score' => 50,
                    'risk_level' => FraudScore::RISK_LEVEL_MEDIUM,
                    'decision' => FraudScore::DECISION_REVIEW,
                    'decision_factors' => ['error' => 'Detection system error - flagged for manual review'],
                    'decision_at' => now(),
                ]);
                
                return $fraudScore;
            }
        });
    }
    
    /**
     * Analyze user account for fraud patterns
     */
    public function analyzeUser(User $user, array $context = []): FraudScore
    {
        $analysisContext = array_merge($context, [
            'user' => $user,
            'account_age_days' => $user->created_at->diffInDays(now()),
            'transaction_history' => $this->getTransactionHistory($user),
        ]);
        
        $fraudScore = FraudScore::create([
            'entity_id' => $user->id,
            'entity_type' => User::class,
            'score_type' => FraudScore::SCORE_TYPE_BATCH,
            'entity_snapshot' => [
                'user_id' => $user->id,
                'created_at' => $user->created_at->toIso8601String(),
                'kyc_level' => $user->kyc_level,
                'risk_rating' => $user->risk_rating,
            ],
        ]);
        
        // Run comprehensive analysis
        $results = $this->performUserAnalysis($user, $analysisContext);
        
        $fraudScore->update([
            'total_score' => $results['total_score'],
            'risk_level' => $results['risk_level'],
            'score_breakdown' => $results['breakdown'],
            'decision' => $results['decision'],
            'decision_factors' => $results['factors'],
            'decision_at' => now(),
        ]);
        
        return $fraudScore;
    }
    
    /**
     * Prepare context for analysis
     */
    protected function prepareContext(Transaction $transaction, User $user, array $additionalContext): array
    {
        return array_merge([
            'transaction' => $transaction->toArray(),
            'user' => $user->toArray(),
            'account' => $transaction->account->toArray(),
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'type' => $transaction->type,
            'timestamp' => $transaction->created_at,
            'metadata' => $transaction->metadata ?? [],
            
            // Velocity metrics
            'daily_transaction_count' => $this->getDailyTransactionCount($user),
            'daily_transaction_volume' => $this->getDailyTransactionVolume($user),
            'hourly_transaction_count' => $this->getHourlyTransactionCount($user),
            
            // Historical data
            'user_transaction_count' => $user->transactions()->count(),
            'account_balance' => $transaction->account->getBalance($transaction->currency),
            
            // Time-based features
            'hour_of_day' => $transaction->created_at->hour,
            'day_of_week' => $transaction->created_at->dayOfWeek,
            'is_weekend' => $transaction->created_at->isWeekend(),
            'time_since_last_transaction' => $this->getTimeSinceLastTransaction($user),
        ], $additionalContext);
    }
    
    /**
     * Create entity snapshot for audit
     */
    protected function createEntitySnapshot(Transaction $transaction): array
    {
        return [
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'type' => $transaction->type,
            'status' => $transaction->status,
            'account_id' => $transaction->account_id,
            'user_id' => $transaction->account->user_id,
            'created_at' => $transaction->created_at->toIso8601String(),
            'metadata' => $transaction->metadata,
        ];
    }
    
    /**
     * Calculate total fraud score
     */
    protected function calculateTotalScore(
        array $ruleResults,
        array $behavioralResults,
        array $deviceResults,
        ?array $mlResults
    ): float {
        $weights = [
            'rules' => 0.35,
            'behavioral' => 0.25,
            'device' => 0.20,
            'ml' => 0.20,
        ];
        
        $score = 0;
        
        // Rule-based score
        $score += ($ruleResults['total_score'] ?? 0) * $weights['rules'];
        
        // Behavioral score
        $score += ($behavioralResults['risk_score'] ?? 0) * $weights['behavioral'];
        
        // Device score
        $score += ($deviceResults['risk_score'] ?? 0) * $weights['device'];
        
        // ML score (if available)
        if ($mlResults) {
            $score += ($mlResults['score'] ?? 0) * $weights['ml'];
        } else {
            // Redistribute ML weight to other components
            $score = $score / (1 - $weights['ml']);
        }
        
        return min(100, max(0, $score));
    }
    
    /**
     * Make decision based on score and rules
     */
    protected function makeDecision(float $score, string $riskLevel, array $ruleResults): string
    {
        // Check for blocking rules
        if (!empty($ruleResults['blocking_rules'])) {
            return FraudScore::DECISION_BLOCK;
        }
        
        // Score-based decision
        if ($score >= 80) {
            return FraudScore::DECISION_BLOCK;
        } elseif ($score >= 60) {
            return FraudScore::DECISION_REVIEW;
        } elseif ($score >= 40) {
            return FraudScore::DECISION_CHALLENGE;
        } else {
            return FraudScore::DECISION_ALLOW;
        }
    }
    
    /**
     * Execute decision actions
     */
    protected function executeDecision(Transaction $transaction, FraudScore $fraudScore, string $decision): void
    {
        switch ($decision) {
            case FraudScore::DECISION_BLOCK:
                $this->blockTransaction($transaction, $fraudScore);
                break;
                
            case FraudScore::DECISION_REVIEW:
                $this->flagForReview($transaction, $fraudScore);
                break;
                
            case FraudScore::DECISION_CHALLENGE:
                $this->requestChallenge($transaction, $fraudScore);
                break;
                
            case FraudScore::DECISION_ALLOW:
                // Transaction proceeds normally
                break;
        }
        
        // Create fraud case for high-risk transactions
        if ($fraudScore->isHighRisk()) {
            $this->caseService->createFromFraudScore($fraudScore);
        }
    }
    
    /**
     * Block transaction
     */
    protected function blockTransaction(Transaction $transaction, FraudScore $fraudScore): void
    {
        $transaction->update([
            'status' => 'blocked',
            'metadata' => array_merge($transaction->metadata ?? [], [
                'blocked_at' => now()->toIso8601String(),
                'block_reason' => 'Fraud detection system',
                'fraud_score_id' => $fraudScore->id,
                'risk_level' => $fraudScore->risk_level,
            ]),
        ]);
        
        event(new TransactionBlocked($transaction, $fraudScore));
        event(new FraudDetected($fraudScore));
    }
    
    /**
     * Flag transaction for review
     */
    protected function flagForReview(Transaction $transaction, FraudScore $fraudScore): void
    {
        $transaction->update([
            'metadata' => array_merge($transaction->metadata ?? [], [
                'requires_review' => true,
                'review_requested_at' => now()->toIso8601String(),
                'fraud_score_id' => $fraudScore->id,
                'risk_level' => $fraudScore->risk_level,
            ]),
        ]);
        
        // Transaction continues but is flagged
        event(new FraudDetected($fraudScore));
    }
    
    /**
     * Request additional authentication
     */
    protected function requestChallenge(Transaction $transaction, FraudScore $fraudScore): void
    {
        $transaction->update([
            'status' => 'pending_challenge',
            'metadata' => array_merge($transaction->metadata ?? [], [
                'challenge_requested_at' => now()->toIso8601String(),
                'challenge_reason' => 'Additional verification required',
                'fraud_score_id' => $fraudScore->id,
            ]),
        ]);
        
        event(new ChallengeRequired($transaction, $fraudScore));
    }
    
    /**
     * Get daily transaction count for user
     */
    protected function getDailyTransactionCount(User $user): int
    {
        return Cache::remember(
            "user_daily_txn_count_{$user->id}",
            60, // 1 minute cache
            function () use ($user) {
                return Transaction::whereHas('account', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->whereDate('created_at', today())
                ->count();
            }
        );
    }
    
    /**
     * Get daily transaction volume for user
     */
    protected function getDailyTransactionVolume(User $user): float
    {
        return Cache::remember(
            "user_daily_txn_volume_{$user->id}",
            60, // 1 minute cache
            function () use ($user) {
                return Transaction::whereHas('account', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->whereDate('created_at', today())
                ->sum('amount');
            }
        );
    }
    
    /**
     * Get hourly transaction count for user
     */
    protected function getHourlyTransactionCount(User $user): int
    {
        return Transaction::whereHas('account', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->where('created_at', '>=', now()->subHour())
        ->count();
    }
    
    /**
     * Get time since last transaction
     */
    protected function getTimeSinceLastTransaction(User $user): ?int
    {
        $lastTransaction = Transaction::whereHas('account', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->orderBy('created_at', 'desc')
        ->skip(1) // Skip current transaction
        ->first();
        
        return $lastTransaction ? $lastTransaction->created_at->diffInMinutes(now()) : null;
    }
    
    /**
     * Get transaction history for analysis
     */
    protected function getTransactionHistory(User $user, int $days = 30): array
    {
        return Transaction::whereHas('account', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->where('created_at', '>=', now()->subDays($days))
        ->select('id', 'amount', 'currency', 'type', 'status', 'created_at')
        ->orderBy('created_at', 'desc')
        ->limit(100)
        ->get()
        ->toArray();
    }
    
    /**
     * Create score breakdown
     */
    protected function createScoreBreakdown(
        array $ruleResults,
        array $behavioralResults,
        array $deviceResults,
        ?array $mlResults
    ): array {
        $breakdown = [];
        
        // Add rule scores
        foreach ($ruleResults['rule_scores'] ?? [] as $ruleCode => $score) {
            $breakdown[] = [
                'component' => 'rule',
                'name' => $ruleCode,
                'score' => $score,
                'severity' => $ruleResults['rule_details'][$ruleCode]['severity'] ?? 'medium',
            ];
        }
        
        // Add behavioral score
        if (isset($behavioralResults['risk_score'])) {
            $breakdown[] = [
                'component' => 'behavioral',
                'name' => 'Behavioral Analysis',
                'score' => $behavioralResults['risk_score'],
                'factors' => $behavioralResults['risk_factors'] ?? [],
            ];
        }
        
        // Add device score
        if (isset($deviceResults['risk_score'])) {
            $breakdown[] = [
                'component' => 'device',
                'name' => 'Device Risk',
                'score' => $deviceResults['risk_score'],
                'factors' => $deviceResults['risk_factors'] ?? [],
            ];
        }
        
        // Add ML score
        if ($mlResults && isset($mlResults['score'])) {
            $breakdown[] = [
                'component' => 'ml',
                'name' => 'Machine Learning Model',
                'score' => $mlResults['score'],
                'confidence' => $mlResults['confidence'] ?? null,
            ];
        }
        
        return $breakdown;
    }
    
    /**
     * Extract network factors
     */
    protected function extractNetworkFactors(array $context): array
    {
        return [
            'ip_address' => $context['ip_address'] ?? null,
            'ip_country' => $context['ip_country'] ?? null,
            'ip_region' => $context['ip_region'] ?? null,
            'isp' => $context['isp'] ?? null,
            'is_vpn' => $context['is_vpn'] ?? false,
            'is_proxy' => $context['is_proxy'] ?? false,
            'is_tor' => $context['is_tor'] ?? false,
        ];
    }
    
    /**
     * Extract decision factors
     */
    protected function extractDecisionFactors(float $score, array $ruleResults): array
    {
        $factors = [
            'total_score' => $score,
            'rules_triggered' => count($ruleResults['triggered_rules'] ?? []),
            'blocking_rules' => $ruleResults['blocking_rules'] ?? [],
        ];
        
        // Add top contributing factors
        if (!empty($ruleResults['rule_scores'])) {
            arsort($ruleResults['rule_scores']);
            $factors['top_rules'] = array_slice($ruleResults['rule_scores'], 0, 3, true);
        }
        
        return $factors;
    }
    
    /**
     * Perform comprehensive user analysis
     */
    protected function performUserAnalysis(User $user, array $context): array
    {
        $scores = [];
        
        // Account age risk
        $accountAge = $context['account_age_days'];
        if ($accountAge < 7) {
            $scores['new_account'] = 30;
        } elseif ($accountAge < 30) {
            $scores['new_account'] = 15;
        }
        
        // Transaction pattern analysis
        $txHistory = $context['transaction_history'];
        if (count($txHistory) > 0) {
            $patternScore = $this->analyzeTransactionPatterns($txHistory);
            if ($patternScore > 0) {
                $scores['suspicious_patterns'] = $patternScore;
            }
        }
        
        // KYC completeness
        if (!$user->kyc_level || $user->kyc_level === 'none') {
            $scores['no_kyc'] = 25;
        } elseif ($user->kyc_level === 'basic') {
            $scores['basic_kyc_only'] = 10;
        }
        
        // Calculate total
        $totalScore = array_sum($scores);
        $riskLevel = FraudScore::calculateRiskLevel($totalScore);
        
        return [
            'total_score' => $totalScore,
            'risk_level' => $riskLevel,
            'breakdown' => $scores,
            'decision' => $totalScore >= 60 ? FraudScore::DECISION_REVIEW : FraudScore::DECISION_ALLOW,
            'factors' => array_keys($scores),
        ];
    }
    
    /**
     * Analyze transaction patterns for suspicious behavior
     */
    protected function analyzeTransactionPatterns(array $transactions): float
    {
        $score = 0;
        
        // Check for rapid transactions
        $rapidTxns = 0;
        for ($i = 1; $i < count($transactions); $i++) {
            $timeDiff = strtotime($transactions[$i-1]['created_at']) - strtotime($transactions[$i]['created_at']);
            if ($timeDiff < 300) { // Less than 5 minutes
                $rapidTxns++;
            }
        }
        
        if ($rapidTxns > 3) {
            $score += 20;
        }
        
        // Check for round amounts
        $roundAmounts = array_filter($transactions, function ($tx) {
            return $tx['amount'] % 100 == 0;
        });
        
        if (count($roundAmounts) / count($transactions) > 0.8) {
            $score += 15;
        }
        
        return $score;
    }
}