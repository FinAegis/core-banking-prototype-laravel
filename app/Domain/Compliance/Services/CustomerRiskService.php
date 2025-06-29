<?php

namespace App\Domain\Compliance\Services;

use App\Models\User;
use App\Models\Account;
use App\Models\CustomerRiskProfile;
use App\Domain\Compliance\Events\RiskLevelChanged;
use App\Domain\Compliance\Events\EnhancedDueDiligenceRequired;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerRiskService
{
    /**
     * Create or update risk profile for user
     */
    public function createOrUpdateProfile(User $user, array $additionalData = []): CustomerRiskProfile
    {
        return DB::transaction(function () use ($user, $additionalData) {
            $profile = CustomerRiskProfile::firstOrNew(['user_id' => $user->id]);
            
            // Set default values for new profiles
            if (!$profile->exists) {
                $profile->fill([
                    'risk_rating' => CustomerRiskProfile::RISK_RATING_LOW,
                    'risk_score' => 0,
                    'cdd_level' => CustomerRiskProfile::CDD_LEVEL_STANDARD,
                    'daily_transaction_limit' => 10000,
                    'monthly_transaction_limit' => 100000,
                    'single_transaction_limit' => 5000,
                    'monitoring_frequency' => 30,
                ]);
            }
            
            // Update with additional data
            $profile->fill($additionalData);
            
            // Calculate initial risk assessment
            $this->performRiskAssessment($profile, $user);
            
            $profile->save();
            
            return $profile;
        });
    }
    
    /**
     * Perform comprehensive risk assessment
     */
    public function performRiskAssessment(CustomerRiskProfile $profile, User $user): void
    {
        // Geographic risk
        $profile->geographic_risk = $this->assessGeographicRisk($user);
        
        // Product risk
        $profile->product_risk = $this->assessProductRisk($user);
        
        // Channel risk
        $profile->channel_risk = $this->assessChannelRisk($user);
        
        // Customer risk
        $profile->customer_risk = $this->assessCustomerRisk($user, $profile);
        
        // Behavioral risk (if existing customer)
        if ($profile->exists) {
            $profile->behavioral_risk = $this->assessBehavioralRisk($user);
        }
        
        // Calculate overall risk score
        $profile->risk_score = $profile->calculateRiskScore();
        $profile->risk_rating = $profile->determineRiskRating();
        $profile->cdd_level = $profile->determineCDDLevel();
        
        // Set review dates
        $profile->last_assessment_at = now();
        $profile->next_review_at = $this->calculateNextReviewDate($profile->risk_rating);
        
        // Apply risk-based limits
        $this->applyRiskBasedLimits($profile);
    }
    
    /**
     * Assess geographic risk
     */
    protected function assessGeographicRisk(User $user): array
    {
        $risk = [
            'countries' => [],
            'score' => 0,
            'factors' => [],
        ];
        
        // User's country
        if ($user->country) {
            $risk['countries'][] = $user->country;
            
            if (in_array($user->country, CustomerRiskProfile::HIGH_RISK_COUNTRIES)) {
                $risk['score'] = 80;
                $risk['factors'][] = 'high_risk_country';
            }
        }
        
        // Check transaction history for other countries
        $transactionCountries = $this->getTransactionCountries($user);
        $risk['countries'] = array_unique(array_merge($risk['countries'], $transactionCountries));
        
        foreach ($transactionCountries as $country) {
            if (in_array($country, CustomerRiskProfile::HIGH_RISK_COUNTRIES)) {
                $risk['score'] = max($risk['score'], 60);
                $risk['factors'][] = 'high_risk_transaction_country';
                break;
            }
        }
        
        return $risk;
    }
    
    /**
     * Assess product risk
     */
    protected function assessProductRisk(User $user): array
    {
        $risk = [
            'products' => [],
            'score' => 20, // Base score
            'factors' => [],
        ];
        
        // Check enabled products/features
        $accounts = $user->accounts;
        
        foreach ($accounts as $account) {
            if ($account->type === 'crypto') {
                $risk['products'][] = 'crypto';
                $risk['score'] = max($risk['score'], 80);
                $risk['factors'][] = 'crypto_enabled';
            }
            
            if ($account->metadata['international_wire_enabled'] ?? false) {
                $risk['products'][] = 'international_wire';
                $risk['score'] = max($risk['score'], 60);
                $risk['factors'][] = 'international_wire_enabled';
            }
        }
        
        return $risk;
    }
    
    /**
     * Assess channel risk
     */
    protected function assessChannelRisk(User $user): array
    {
        $risk = [
            'onboarding_channel' => 'online_verified',
            'score' => 30,
            'factors' => [],
        ];
        
        // Check KYC verification level
        if ($user->kyc_level === 'full') {
            $risk['onboarding_channel'] = 'online_verified';
            $risk['score'] = 30;
        } elseif ($user->kyc_level === 'basic') {
            $risk['onboarding_channel'] = 'online_unverified';
            $risk['score'] = 60;
            $risk['factors'][] = 'limited_verification';
        } else {
            $risk['onboarding_channel'] = 'online_unverified';
            $risk['score'] = 80;
            $risk['factors'][] = 'no_verification';
        }
        
        return $risk;
    }
    
    /**
     * Assess customer-specific risk
     */
    protected function assessCustomerRisk(User $user, CustomerRiskProfile $profile): array
    {
        $risk = [
            'factors' => [],
            'score' => 20, // Base score
        ];
        
        // PEP status
        if ($profile->is_pep) {
            $risk['factors'][] = 'pep';
            $risk['score'] = max($risk['score'], 80);
        }
        
        // Sanctions
        if ($profile->is_sanctioned) {
            $risk['factors'][] = 'sanctioned';
            $risk['score'] = 100;
        }
        
        // Adverse media
        if ($profile->has_adverse_media) {
            $risk['factors'][] = 'adverse_media';
            $risk['score'] = max($risk['score'], 70);
        }
        
        // Business customers
        if ($profile->business_type) {
            $risk['factors'][] = 'business_account';
            $risk['score'] = max($risk['score'], 40);
            
            // High-risk industries
            if (in_array($profile->industry_code, CustomerRiskProfile::HIGH_RISK_INDUSTRIES)) {
                $risk['factors'][] = 'high_risk_industry';
                $risk['score'] = max($risk['score'], 70);
            }
            
            // Complex structure
            if ($profile->complex_structure) {
                $risk['factors'][] = 'complex_structure';
                $risk['score'] = max($risk['score'], 60);
            }
        }
        
        return $risk;
    }
    
    /**
     * Assess behavioral risk
     */
    protected function assessBehavioralRisk(User $user): array
    {
        $risk = [
            'patterns' => [],
            'score' => 20, // Base score
            'factors' => [],
        ];
        
        // Get transaction statistics
        $stats = $this->getTransactionStatistics($user);
        
        // Store behavioral baseline
        $risk['avg_transaction_amount'] = $stats['avg_amount'];
        $risk['transaction_amount_std_dev'] = $stats['std_dev'];
        $risk['avg_daily_transactions'] = $stats['avg_daily_count'];
        $risk['usual_transaction_hours'] = $stats['usual_hours'];
        $risk['known_destinations'] = $stats['destinations'];
        
        // Check for unusual patterns
        if ($stats['rapid_growth']) {
            $risk['patterns'][] = 'rapid_volume_increase';
            $risk['score'] = max($risk['score'], 60);
        }
        
        if ($stats['unusual_hours']) {
            $risk['patterns'][] = 'unusual_transaction_times';
            $risk['score'] = max($risk['score'], 40);
        }
        
        return $risk;
    }
    
    /**
     * Apply risk-based transaction limits
     */
    protected function applyRiskBasedLimits(CustomerRiskProfile $profile): void
    {
        switch ($profile->risk_rating) {
            case CustomerRiskProfile::RISK_RATING_HIGH:
                $profile->daily_transaction_limit = 5000;
                $profile->monthly_transaction_limit = 50000;
                $profile->single_transaction_limit = 2000;
                $profile->enhanced_monitoring = true;
                $profile->monitoring_frequency = 7; // Weekly review
                break;
                
            case CustomerRiskProfile::RISK_RATING_MEDIUM:
                $profile->daily_transaction_limit = 10000;
                $profile->monthly_transaction_limit = 100000;
                $profile->single_transaction_limit = 5000;
                $profile->enhanced_monitoring = false;
                $profile->monitoring_frequency = 30; // Monthly review
                break;
                
            case CustomerRiskProfile::RISK_RATING_LOW:
                $profile->daily_transaction_limit = 25000;
                $profile->monthly_transaction_limit = 250000;
                $profile->single_transaction_limit = 10000;
                $profile->enhanced_monitoring = false;
                $profile->monitoring_frequency = 90; // Quarterly review
                break;
                
            case CustomerRiskProfile::RISK_RATING_PROHIBITED:
                $profile->daily_transaction_limit = 0;
                $profile->monthly_transaction_limit = 0;
                $profile->single_transaction_limit = 0;
                $profile->enhanced_monitoring = true;
                $profile->monitoring_frequency = 1; // Daily monitoring
                break;
        }
    }
    
    /**
     * Escalate risk for account
     */
    public function escalateRiskForAccount(string $accountId, string $reason): void
    {
        $account = Account::find($accountId);
        if (!$account || !$account->user_id) {
            return;
        }
        
        $profile = CustomerRiskProfile::where('user_id', $account->user_id)->first();
        if (!$profile) {
            return;
        }
        
        $oldRating = $profile->risk_rating;
        
        // Increase suspicious activity count
        $profile->increment('suspicious_activities_count');
        $profile->last_suspicious_activity_at = now();
        
        // Add to behavioral patterns
        $behavioralRisk = $profile->behavioral_risk ?? [];
        $behavioralRisk['patterns'][] = $reason;
        $behavioralRisk['escalation_history'][] = [
            'date' => now()->toIso8601String(),
            'reason' => $reason,
        ];
        $profile->behavioral_risk = $behavioralRisk;
        
        // Recalculate risk
        $profile->updateRiskAssessment();
        
        // Trigger events if risk level changed
        if ($oldRating !== $profile->risk_rating) {
            event(new RiskLevelChanged($profile, $oldRating, $profile->risk_rating));
            
            if ($profile->requiresEnhancedDueDiligence()) {
                event(new EnhancedDueDiligenceRequired($profile));
            }
        }
    }
    
    /**
     * Get transaction countries for user
     */
    protected function getTransactionCountries(User $user): array
    {
        // In production, query transaction metadata for countries
        // For now, return empty array
        return [];
    }
    
    /**
     * Get transaction statistics for user
     */
    protected function getTransactionStatistics(User $user): array
    {
        // In production, calculate from actual transaction data
        // For now, return simulated statistics
        return [
            'avg_amount' => 500,
            'std_dev' => 100,
            'avg_daily_count' => 2,
            'usual_hours' => range(9, 17), // 9 AM to 5 PM
            'destinations' => ['US', 'CA', 'GB'],
            'rapid_growth' => false,
            'unusual_hours' => false,
        ];
    }
    
    /**
     * Calculate next review date based on risk rating
     */
    protected function calculateNextReviewDate(string $riskRating): \Carbon\Carbon
    {
        return match($riskRating) {
            CustomerRiskProfile::RISK_RATING_HIGH => now()->addMonth(),
            CustomerRiskProfile::RISK_RATING_MEDIUM => now()->addMonths(3),
            CustomerRiskProfile::RISK_RATING_LOW => now()->addMonths(6),
            CustomerRiskProfile::RISK_RATING_PROHIBITED => now()->addDay(),
            default => now()->addMonth(),
        };
    }
    
    /**
     * Check if customer can perform transaction
     */
    public function canPerformTransaction(User $user, float $amount, string $currency): array
    {
        $profile = CustomerRiskProfile::where('user_id', $user->id)->first();
        
        if (!$profile) {
            return [
                'allowed' => true,
                'reason' => null,
            ];
        }
        
        // Check if prohibited
        if ($profile->isProhibited()) {
            return [
                'allowed' => false,
                'reason' => 'Account is prohibited from transactions',
            ];
        }
        
        // Check single transaction limit
        if ($amount > $profile->single_transaction_limit) {
            return [
                'allowed' => false,
                'reason' => 'Transaction exceeds single transaction limit',
                'limit' => $profile->single_transaction_limit,
            ];
        }
        
        // Check daily limit
        $dailyTotal = $this->getDailyTransactionTotal($user);
        if (($dailyTotal + $amount) > $profile->daily_transaction_limit) {
            return [
                'allowed' => false,
                'reason' => 'Transaction would exceed daily limit',
                'limit' => $profile->daily_transaction_limit,
                'current' => $dailyTotal,
            ];
        }
        
        // Check monthly limit
        $monthlyTotal = $this->getMonthlyTransactionTotal($user);
        if (($monthlyTotal + $amount) > $profile->monthly_transaction_limit) {
            return [
                'allowed' => false,
                'reason' => 'Transaction would exceed monthly limit',
                'limit' => $profile->monthly_transaction_limit,
                'current' => $monthlyTotal,
            ];
        }
        
        // Check currency restrictions
        if (!empty($profile->restricted_currencies) && in_array($currency, $profile->restricted_currencies)) {
            return [
                'allowed' => false,
                'reason' => 'Currency is restricted for this account',
                'currency' => $currency,
            ];
        }
        
        return [
            'allowed' => true,
            'reason' => null,
            'enhanced_monitoring' => $profile->enhanced_monitoring,
        ];
    }
    
    /**
     * Get daily transaction total
     */
    protected function getDailyTransactionTotal(User $user): float
    {
        // In production, calculate from actual transactions
        return 0;
    }
    
    /**
     * Get monthly transaction total
     */
    protected function getMonthlyTransactionTotal(User $user): float
    {
        // In production, calculate from actual transactions
        return 0;
    }
}