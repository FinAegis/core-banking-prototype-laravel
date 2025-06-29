<?php

namespace App\Domain\Fraud\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\BehavioralProfile;
use App\Models\FraudScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BehavioralAnalysisService
{
    /**
     * Analyze user behavior for anomalies
     */
    public function analyze(User $user, Transaction $transaction, array $context): array
    {
        $profile = $this->getOrCreateProfile($user);
        
        // If profile not established, return neutral score
        if (!$profile->isEstablished()) {
            return [
                'risk_score' => 30, // Slightly elevated for new users
                'is_established' => false,
                'risk_factors' => ['new_user_profile'],
            ];
        }
        
        $riskFactors = [];
        $riskScore = 0;
        
        // 1. Transaction timing analysis
        $timingAnalysis = $this->analyzeTransactionTiming($profile, $transaction);
        if ($timingAnalysis['is_unusual']) {
            $riskFactors[] = 'unusual_transaction_time';
            $riskScore += $timingAnalysis['risk_contribution'];
        }
        
        // 2. Transaction amount analysis
        $amountAnalysis = $this->analyzeTransactionAmount($profile, $transaction);
        if ($amountAnalysis['is_unusual']) {
            $riskFactors[] = 'unusual_transaction_amount';
            $riskScore += $amountAnalysis['risk_contribution'];
        }
        
        // 3. Location analysis
        $locationAnalysis = $this->analyzeLocation($profile, $context);
        if ($locationAnalysis['is_unusual']) {
            $riskFactors[] = 'unusual_location';
            $riskScore += $locationAnalysis['risk_contribution'];
        }
        
        // 4. Device analysis
        $deviceAnalysis = $this->analyzeDevice($profile, $context);
        if ($deviceAnalysis['is_unusual']) {
            $riskFactors[] = 'unusual_device';
            $riskScore += $deviceAnalysis['risk_contribution'];
        }
        
        // 5. Transaction pattern analysis
        $patternAnalysis = $this->analyzeTransactionPatterns($profile, $transaction, $context);
        if ($patternAnalysis['has_suspicious_patterns']) {
            $riskFactors = array_merge($riskFactors, $patternAnalysis['patterns']);
            $riskScore += $patternAnalysis['risk_contribution'];
        }
        
        // 6. Velocity analysis
        $velocityAnalysis = $this->analyzeVelocity($profile, $context);
        if ($velocityAnalysis['exceeds_normal']) {
            $riskFactors[] = 'high_velocity';
            $riskScore += $velocityAnalysis['risk_contribution'];
        }
        
        // 7. Merchant/recipient analysis
        $recipientAnalysis = $this->analyzeRecipient($profile, $transaction);
        if ($recipientAnalysis['is_unusual']) {
            $riskFactors[] = 'unusual_recipient';
            $riskScore += $recipientAnalysis['risk_contribution'];
        }
        
        // Calculate behavioral deviation score
        $deviationScore = $profile->calculateBehaviorScore([
            'hour' => $transaction->created_at->hour,
            'amount' => $transaction->amount,
            'country' => $context['ip_country'] ?? null,
            'device_id' => $context['device_data']['fingerprint_id'] ?? null,
            'daily_count' => $context['daily_transaction_count'] ?? 0,
        ]);
        
        // Combine scores
        $finalScore = min(100, ($riskScore + $deviationScore) / 2);
        
        return [
            'risk_score' => $finalScore,
            'deviation_score' => $deviationScore,
            'risk_factors' => $riskFactors,
            'is_established' => true,
            'profile_confidence' => $this->calculateProfileConfidence($profile),
            'analysis_details' => [
                'timing' => $timingAnalysis,
                'amount' => $amountAnalysis,
                'location' => $locationAnalysis,
                'device' => $deviceAnalysis,
                'patterns' => $patternAnalysis,
                'velocity' => $velocityAnalysis,
                'recipient' => $recipientAnalysis,
            ],
        ];
    }
    
    /**
     * Get or create behavioral profile
     */
    protected function getOrCreateProfile(User $user): BehavioralProfile
    {
        return BehavioralProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'typical_transaction_times' => array_fill(0, 24, 0),
                'typical_transaction_days' => array_fill(0, 7, 0),
                'profile_established_at' => now(),
            ]
        );
    }
    
    /**
     * Analyze transaction timing
     */
    protected function analyzeTransactionTiming(BehavioralProfile $profile, Transaction $transaction): array
    {
        $hour = $transaction->created_at->hour;
        $dayOfWeek = $transaction->created_at->dayOfWeek;
        
        $isUnusualTime = $profile->isTransactionTimeUnusual($hour);
        $isUnusualDay = false;
        
        // Check day of week pattern
        if ($profile->typical_transaction_days) {
            $dayPercentage = $profile->typical_transaction_days[$dayOfWeek] ?? 0;
            $isUnusualDay = $dayPercentage < 5; // Less than 5% of transactions on this day
        }
        
        $riskContribution = 0;
        if ($isUnusualTime) $riskContribution += 15;
        if ($isUnusualDay) $riskContribution += 10;
        
        // Night transactions (midnight to 5am) are higher risk
        if ($hour >= 0 && $hour < 5) {
            $riskContribution += 10;
        }
        
        return [
            'is_unusual' => $isUnusualTime || $isUnusualDay,
            'unusual_time' => $isUnusualTime,
            'unusual_day' => $isUnusualDay,
            'hour' => $hour,
            'day_of_week' => $dayOfWeek,
            'risk_contribution' => $riskContribution,
        ];
    }
    
    /**
     * Analyze transaction amount
     */
    protected function analyzeTransactionAmount(BehavioralProfile $profile, Transaction $transaction): array
    {
        $amount = $transaction->amount;
        $isUnusual = $profile->isTransactionAmountUnusual($amount);
        
        $riskContribution = 0;
        
        if ($isUnusual) {
            // Calculate how unusual
            if ($profile->avg_transaction_amount > 0) {
                $deviation = abs($amount - $profile->avg_transaction_amount) / $profile->avg_transaction_amount;
                
                if ($deviation > 10) {
                    $riskContribution = 40; // Very unusual
                } elseif ($deviation > 5) {
                    $riskContribution = 25; // Moderately unusual
                } else {
                    $riskContribution = 15; // Slightly unusual
                }
            } else {
                $riskContribution = 20;
            }
        }
        
        return [
            'is_unusual' => $isUnusual,
            'amount' => $amount,
            'average_amount' => $profile->avg_transaction_amount,
            'deviation' => $profile->avg_transaction_amount > 0 ? 
                round(($amount / $profile->avg_transaction_amount - 1) * 100, 2) : null,
            'risk_contribution' => $riskContribution,
        ];
    }
    
    /**
     * Analyze location
     */
    protected function analyzeLocation(BehavioralProfile $profile, array $context): array
    {
        $country = $context['ip_country'] ?? null;
        $city = $context['ip_city'] ?? null;
        
        if (!$country) {
            return [
                'is_unusual' => false,
                'risk_contribution' => 0,
            ];
        }
        
        $isUnusual = $profile->isLocationUnusual($country, $city);
        $riskContribution = 0;
        
        if ($isUnusual) {
            // New country is higher risk than new city
            if ($country !== $profile->primary_country) {
                $riskContribution = 30;
                
                // Even higher if it's a high-risk country
                if (in_array($country, ['NG', 'PK', 'ID', 'VN', 'BD'])) {
                    $riskContribution = 45;
                }
            } else {
                $riskContribution = 15; // New city in same country
            }
        }
        
        // Update location history
        $profile->updateLocationHistory($country, $city, $context['ip_address'] ?? null);
        
        return [
            'is_unusual' => $isUnusual,
            'country' => $country,
            'city' => $city,
            'primary_country' => $profile->primary_country,
            'risk_contribution' => $riskContribution,
        ];
    }
    
    /**
     * Analyze device
     */
    protected function analyzeDevice(BehavioralProfile $profile, array $context): array
    {
        $deviceId = $context['device_data']['fingerprint_id'] ?? null;
        
        if (!$deviceId) {
            return [
                'is_unusual' => true,
                'reason' => 'no_device_fingerprint',
                'risk_contribution' => 20,
            ];
        }
        
        $isUnusual = $profile->isDeviceUnusual($deviceId);
        $riskContribution = 0;
        
        if ($isUnusual) {
            $riskContribution = 25;
            
            // Check device risk factors
            $deviceRisk = $context['device_data']['risk_score'] ?? 0;
            if ($deviceRisk > 70) {
                $riskContribution += 20;
            }
        }
        
        return [
            'is_unusual' => $isUnusual,
            'device_id' => substr($deviceId, 0, 8) . '...',
            'is_trusted' => in_array($deviceId, $profile->trusted_devices ?? []),
            'device_count' => $profile->device_count,
            'risk_contribution' => $riskContribution,
        ];
    }
    
    /**
     * Analyze transaction patterns
     */
    protected function analyzeTransactionPatterns(
        BehavioralProfile $profile,
        Transaction $transaction,
        array $context
    ): array {
        $patterns = [];
        $riskContribution = 0;
        
        // Check for account draining
        if ($this->detectAccountDraining($transaction, $context)) {
            $patterns[] = 'account_draining';
            $riskContribution += 35;
        }
        
        // Check for unusual sequencing
        if ($this->detectUnusualSequencing($context)) {
            $patterns[] = 'unusual_sequence';
            $riskContribution += 20;
        }
        
        // Check for dormant account suddenly active
        if ($this->detectDormantAccountActivity($profile, $context)) {
            $patterns[] = 'dormant_account_active';
            $riskContribution += 30;
        }
        
        // Check for sudden pattern change
        if ($this->detectPatternChange($profile, $context)) {
            $patterns[] = 'sudden_pattern_change';
            $riskContribution += 25;
        }
        
        return [
            'has_suspicious_patterns' => !empty($patterns),
            'patterns' => $patterns,
            'risk_contribution' => $riskContribution,
        ];
    }
    
    /**
     * Analyze velocity
     */
    protected function analyzeVelocity(BehavioralProfile $profile, array $context): array
    {
        $exceedsNormal = false;
        $riskContribution = 0;
        $reasons = [];
        
        // Daily transaction count
        $dailyCount = $context['daily_transaction_count'] ?? 0;
        if ($profile->avg_daily_transaction_count > 0 && 
            $dailyCount > ($profile->avg_daily_transaction_count * 3)) {
            $exceedsNormal = true;
            $reasons[] = 'high_daily_count';
            $riskContribution += 20;
        }
        
        // Daily volume
        $dailyVolume = $context['daily_transaction_volume'] ?? 0;
        if ($profile->max_daily_volume > 0 && $dailyVolume > $profile->max_daily_volume) {
            $exceedsNormal = true;
            $reasons[] = 'exceeds_max_daily_volume';
            $riskContribution += 25;
        }
        
        // Hourly velocity
        $hourlyCount = $context['hourly_transaction_count'] ?? 0;
        if ($hourlyCount > 5) {
            $exceedsNormal = true;
            $reasons[] = 'high_hourly_velocity';
            $riskContribution += 15;
        }
        
        return [
            'exceeds_normal' => $exceedsNormal,
            'reasons' => $reasons,
            'daily_count' => $dailyCount,
            'avg_daily_count' => $profile->avg_daily_transaction_count,
            'risk_contribution' => $riskContribution,
        ];
    }
    
    /**
     * Analyze recipient
     */
    protected function analyzeRecipient(BehavioralProfile $profile, Transaction $transaction): array
    {
        $recipientId = $transaction->metadata['recipient_account_id'] ?? null;
        $merchantId = $transaction->metadata['merchant_id'] ?? null;
        
        if (!$recipientId && !$merchantId) {
            return [
                'is_unusual' => false,
                'risk_contribution' => 0,
            ];
        }
        
        $isUnusual = false;
        $riskContribution = 0;
        
        // Check if recipient is in frequent list
        $frequentRecipients = $profile->frequent_recipients ?? [];
        $frequentMerchants = $profile->frequent_merchants ?? [];
        
        if ($recipientId && !in_array($recipientId, $frequentRecipients)) {
            $isUnusual = true;
            $riskContribution = 15;
        }
        
        if ($merchantId && !in_array($merchantId, $frequentMerchants)) {
            $isUnusual = true;
            $riskContribution = 10; // Lower risk for new merchants
        }
        
        return [
            'is_unusual' => $isUnusual,
            'is_new_recipient' => $recipientId && !in_array($recipientId, $frequentRecipients),
            'is_new_merchant' => $merchantId && !in_array($merchantId, $frequentMerchants),
            'risk_contribution' => $riskContribution,
        ];
    }
    
    /**
     * Update user profile after transaction
     */
    public function updateProfile(User $user, Transaction $transaction, FraudScore $fraudScore): void
    {
        $profile = $this->getOrCreateProfile($user);
        
        DB::transaction(function () use ($profile, $transaction, $fraudScore) {
            // Update transaction statistics
            $this->updateTransactionStats($profile, $transaction);
            
            // Update device trust if legitimate
            if ($fraudScore->decision === FraudScore::DECISION_ALLOW) {
                $deviceId = $transaction->metadata['device_fingerprint_id'] ?? null;
                if ($deviceId && !in_array($deviceId, $profile->trusted_devices ?? [])) {
                    $profile->addTrustedDevice($deviceId);
                }
            }
            
            // Mark suspicious activity if fraud detected
            if ($fraudScore->isHighRisk()) {
                $profile->update([
                    'last_suspicious_activity' => now(),
                ]);
                $profile->increment('suspicious_activities_count');
            }
            
            // Update profile maturity
            $this->updateProfileMaturity($profile);
        });
    }
    
    /**
     * Update transaction statistics
     */
    protected function updateTransactionStats(BehavioralProfile $profile, Transaction $transaction): void
    {
        // Get recent transactions for statistics
        $recentTransactions = Transaction::whereHas('account', function ($query) use ($profile) {
            $query->where('user_id', $profile->user_id);
        })
        ->where('created_at', '>=', now()->subDays(90))
        ->orderBy('created_at', 'desc')
        ->limit(100)
        ->get();
        
        $profile->updateTransactionStats($recentTransactions->toArray());
        
        // Update counts
        $profile->increment('total_transaction_count');
        $profile->increment('total_transaction_volume', $transaction->amount);
        
        // Update maximums
        if ($transaction->amount > $profile->max_transaction_amount) {
            $profile->update(['max_transaction_amount' => $transaction->amount]);
        }
        
        // Update daily counts
        $todayCount = Transaction::whereHas('account', function ($query) use ($profile) {
            $query->where('user_id', $profile->user_id);
        })
        ->whereDate('created_at', today())
        ->count();
        
        if ($todayCount > $profile->max_daily_transactions) {
            $profile->update(['max_daily_transactions' => $todayCount]);
        }
    }
    
    /**
     * Update profile maturity
     */
    protected function updateProfileMaturity(BehavioralProfile $profile): void
    {
        $daysSinceFirst = $profile->created_at->diffInDays(now());
        
        $profile->update([
            'days_since_first_transaction' => $daysSinceFirst,
            'is_established' => $daysSinceFirst >= 30 && $profile->total_transaction_count >= 10,
        ]);
        
        // Generate ML features if established
        if ($profile->is_established) {
            $profile->generateMLFeatures();
        }
    }
    
    /**
     * Calculate profile confidence
     */
    protected function calculateProfileConfidence(BehavioralProfile $profile): float
    {
        $confidence = 0;
        
        // Account age factor
        if ($profile->days_since_first_transaction >= 180) {
            $confidence += 30;
        } elseif ($profile->days_since_first_transaction >= 90) {
            $confidence += 20;
        } elseif ($profile->days_since_first_transaction >= 30) {
            $confidence += 10;
        }
        
        // Transaction count factor
        if ($profile->total_transaction_count >= 100) {
            $confidence += 30;
        } elseif ($profile->total_transaction_count >= 50) {
            $confidence += 20;
        } elseif ($profile->total_transaction_count >= 20) {
            $confidence += 10;
        }
        
        // Consistency factor
        if ($profile->profile_change_frequency < 5) {
            $confidence += 20;
        }
        
        // Security factor
        if ($profile->uses_2fa) {
            $confidence += 10;
        }
        
        // No suspicious activity bonus
        if (!$profile->last_suspicious_activity || 
            $profile->last_suspicious_activity->diffInDays(now()) > 90) {
            $confidence += 10;
        }
        
        return min(100, $confidence);
    }
    
    /**
     * Detect account draining pattern
     */
    protected function detectAccountDraining(Transaction $transaction, array $context): bool
    {
        if ($transaction->type !== 'withdrawal') {
            return false;
        }
        
        $balance = $context['account_balance'] ?? 0;
        $amount = $transaction->amount;
        
        // Withdrawing more than 80% of balance
        return $balance > 0 && ($amount / $balance) > 0.8;
    }
    
    /**
     * Detect unusual sequencing
     */
    protected function detectUnusualSequencing(array $context): bool
    {
        $lastTxnType = $context['last_transaction_type'] ?? null;
        $currentType = $context['type'] ?? null;
        $timeSinceLast = $context['time_since_last_transaction'] ?? null;
        
        // Quick deposit->withdrawal sequence
        if ($lastTxnType === 'deposit' && 
            $currentType === 'withdrawal' && 
            $timeSinceLast !== null && 
            $timeSinceLast < 30) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Detect dormant account activity
     */
    protected function detectDormantAccountActivity(BehavioralProfile $profile, array $context): bool
    {
        $lastActivity = $profile->updated_at;
        $daysSinceActivity = $lastActivity->diffInDays(now());
        
        // Account dormant for more than 90 days suddenly active
        return $daysSinceActivity > 90;
    }
    
    /**
     * Detect sudden pattern change
     */
    protected function detectPatternChange(BehavioralProfile $profile, array $context): bool
    {
        // Multiple indicators of change
        $changes = 0;
        
        // New device
        if (isset($context['device_data']['fingerprint_id']) && 
            !in_array($context['device_data']['fingerprint_id'], $profile->trusted_devices ?? [])) {
            $changes++;
        }
        
        // New location
        if (isset($context['ip_country']) && 
            $context['ip_country'] !== $profile->primary_country) {
            $changes++;
        }
        
        // Unusual time
        if (isset($context['hour_of_day']) && 
            $profile->isTransactionTimeUnusual($context['hour_of_day'])) {
            $changes++;
        }
        
        // Multiple changes indicate potential account compromise
        return $changes >= 2;
    }
}