<?php

namespace App\Services\Cgo;

use App\Domain\Compliance\Services\KycService;
use App\Domain\Compliance\Services\CustomerRiskService;
use App\Domain\Compliance\Services\EnhancedKycService;
use App\Models\CgoInvestment;
use App\Models\KycVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CgoKycService
{
    protected KycService $kycService;
    protected CustomerRiskService $riskService;
    protected EnhancedKycService $enhancedKycService;
    
    // Investment thresholds that trigger different KYC levels
    const BASIC_KYC_THRESHOLD = 1000;      // Up to $1,000
    const ENHANCED_KYC_THRESHOLD = 10000;  // Up to $10,000
    const FULL_KYC_THRESHOLD = 50000;      // Above $50,000
    
    public function __construct(
        KycService $kycService,
        CustomerRiskService $riskService,
        EnhancedKycService $enhancedKycService
    ) {
        $this->kycService = $kycService;
        $this->riskService = $riskService;
        $this->enhancedKycService = $enhancedKycService;
    }

    /**
     * Check KYC requirements for CGO investment
     */
    public function checkKycRequirements(CgoInvestment $investment): array
    {
        $user = $investment->user;
        $totalInvested = $this->getTotalInvestedAmount($user);
        $proposedTotal = $totalInvested + $investment->amount;
        
        // Determine required KYC level based on total investment
        $requiredLevel = $this->determineRequiredKycLevel($proposedTotal);
        
        // Check current KYC status
        $currentKycStatus = $this->getCurrentKycStatus($user);
        
        // Determine if KYC is sufficient
        $isKycSufficient = $this->isKycSufficient($currentKycStatus, $requiredLevel);
        
        return [
            'required_level' => $requiredLevel,
            'current_level' => $currentKycStatus['level'] ?? null,
            'current_status' => $currentKycStatus['status'] ?? 'none',
            'is_sufficient' => $isKycSufficient,
            'total_invested' => $totalInvested,
            'proposed_total' => $proposedTotal,
            'required_documents' => $this->getRequiredDocuments($requiredLevel),
            'additional_checks' => $this->getAdditionalChecks($requiredLevel),
        ];
    }

    /**
     * Verify investor for CGO investment
     */
    public function verifyInvestor(CgoInvestment $investment): bool
    {
        $user = $investment->user;
        $kycRequirements = $this->checkKycRequirements($investment);
        
        if (!$kycRequirements['is_sufficient']) {
            Log::warning('CGO investment blocked - insufficient KYC', [
                'investment_id' => $investment->id,
                'user_id' => $user->id,
                'required_level' => $kycRequirements['required_level'],
                'current_level' => $kycRequirements['current_level'],
            ]);
            
            $investment->update([
                'status' => 'kyc_required',
                'notes' => 'Investment requires ' . $kycRequirements['required_level'] . ' KYC verification',
            ]);
            
            return false;
        }
        
        // Perform additional AML checks for high-value investments
        if ($investment->amount >= self::ENHANCED_KYC_THRESHOLD) {
            $amlCheckResult = $this->performAmlChecks($user, $investment);
            
            if (!$amlCheckResult['passed']) {
                Log::warning('CGO investment flagged by AML checks', [
                    'investment_id' => $investment->id,
                    'user_id' => $user->id,
                    'flags' => $amlCheckResult['flags'],
                ]);
                
                $investment->update([
                    'status' => 'aml_review',
                    'notes' => 'Investment flagged for AML review: ' . implode(', ', $amlCheckResult['flags']),
                ]);
                
                return false;
            }
        }
        
        // Update investment with KYC verification details
        $investment->update([
            'kyc_verified_at' => now(),
            'kyc_level' => $kycRequirements['current_level'],
            'risk_assessment' => $this->riskService->calculateRiskScore($user),
        ]);
        
        return true;
    }

    /**
     * Get total invested amount by user
     */
    protected function getTotalInvestedAmount(User $user): float
    {
        return CgoInvestment::where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->sum('amount');
    }

    /**
     * Determine required KYC level based on investment amount
     */
    protected function determineRequiredKycLevel(float $amount): string
    {
        if ($amount <= self::BASIC_KYC_THRESHOLD) {
            return 'basic';
        } elseif ($amount <= self::ENHANCED_KYC_THRESHOLD) {
            return 'enhanced';
        } else {
            return 'full';
        }
    }

    /**
     * Get current KYC status for user
     */
    protected function getCurrentKycStatus(User $user): array
    {
        // Check for expired KYC
        $this->kycService->checkExpiredKyc($user);
        
        return [
            'status' => $user->kyc_status,
            'level' => $user->kyc_level,
            'approved_at' => $user->kyc_approved_at,
            'expires_at' => $user->kyc_expires_at,
            'risk_rating' => $user->risk_rating,
            'pep_status' => $user->pep_status,
        ];
    }

    /**
     * Check if current KYC is sufficient for required level
     */
    protected function isKycSufficient(array $currentStatus, string $requiredLevel): bool
    {
        // Must have approved KYC
        if ($currentStatus['status'] !== 'approved') {
            return false;
        }
        
        // Check if KYC is expired
        if ($currentStatus['expires_at'] && $currentStatus['expires_at']->isPast()) {
            return false;
        }
        
        // Check level hierarchy
        $levels = ['basic' => 1, 'enhanced' => 2, 'full' => 3];
        $currentLevelValue = $levels[$currentStatus['level']] ?? 0;
        $requiredLevelValue = $levels[$requiredLevel] ?? 3;
        
        return $currentLevelValue >= $requiredLevelValue;
    }

    /**
     * Get required documents for KYC level
     */
    protected function getRequiredDocuments(string $level): array
    {
        return $this->kycService->getRequirements($level)['documents'];
    }

    /**
     * Get additional checks required for level
     */
    protected function getAdditionalChecks(string $level): array
    {
        $checks = [];
        
        if ($level === 'enhanced' || $level === 'full') {
            $checks[] = 'pep_screening';
            $checks[] = 'sanctions_screening';
            $checks[] = 'adverse_media_check';
        }
        
        if ($level === 'full') {
            $checks[] = 'source_of_wealth';
            $checks[] = 'source_of_funds';
            $checks[] = 'financial_profile';
        }
        
        return $checks;
    }

    /**
     * Perform AML checks for investment
     */
    protected function performAmlChecks(User $user, CgoInvestment $investment): array
    {
        $flags = [];
        $passed = true;
        
        // Check PEP status
        if ($user->pep_status) {
            $flags[] = 'pep_status';
            $passed = false;
        }
        
        // Check sanctions
        $sanctionsCheck = $this->checkSanctions($user);
        
        if (!$sanctionsCheck['clear']) {
            $flags[] = 'sanctions_hit';
            $passed = false;
        }
        
        // Check transaction patterns
        $transactionCheck = $this->checkTransactionPatterns($user, $investment);
        if (!$transactionCheck['normal']) {
            $flags[] = 'unusual_transaction_pattern';
            $passed = false;
        }
        
        // Check source of funds for large investments
        if ($investment->amount >= self::FULL_KYC_THRESHOLD) {
            $sourceCheck = $this->checkSourceOfFunds($user, $investment);
            if (!$sourceCheck['verified']) {
                $flags[] = 'unverified_source_of_funds';
                $passed = false;
            }
        }
        
        // Risk-based checks
        if ($user->risk_rating === 'high') {
            $flags[] = 'high_risk_profile';
            $passed = false;
        }
        
        return [
            'passed' => $passed,
            'flags' => $flags,
            'timestamp' => now(),
        ];
    }

    /**
     * Check sanctions lists
     */
    protected function checkSanctions(User $user): array
    {
        // In production, this would integrate with sanctions screening APIs
        // For now, we'll do a basic check
        
        $sanctionedCountries = ['IR', 'KP', 'SY', 'CU']; // Iran, North Korea, Syria, Cuba
        
        // Check if user has country_code attribute and if it's sanctioned
        $countryCode = $user->country_code ?? null;
        
        if ($countryCode && in_array($countryCode, $sanctionedCountries)) {
            return ['clear' => false, 'reason' => 'sanctioned_country'];
        }
        
        // Check if user has existing sanctions verification
        $recentVerification = KycVerification::where('user_id', $user->id)
            ->where('type', KycVerification::TYPE_ENHANCED_DUE_DILIGENCE)
            ->where('status', KycVerification::STATUS_COMPLETED)
            ->where('sanctions_check', true)
            ->where('created_at', '>', now()->subMonths(6))
            ->first();
            
        if ($recentVerification) {
            return ['clear' => true, 'verification_id' => $recentVerification->id];
        }
        
        return ['clear' => true, 'requires_verification' => true];
    }

    /**
     * Check transaction patterns
     */
    protected function checkTransactionPatterns(User $user, CgoInvestment $investment): array
    {
        // Check for rapid successive investments
        $recentInvestments = CgoInvestment::where('user_id', $user->id)
            ->where('created_at', '>', now()->subDays(7))
            ->count();
            
        if ($recentInvestments > 3) {
            return ['normal' => false, 'reason' => 'rapid_successive_investments'];
        }
        
        // Check for significant increase in investment amount
        $averageInvestment = CgoInvestment::where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->avg('amount') ?: 0;
            
        if ($averageInvestment > 0 && $investment->amount > ($averageInvestment * 5)) {
            return ['normal' => false, 'reason' => 'significant_amount_increase'];
        }
        
        return ['normal' => true];
    }

    /**
     * Check source of funds
     */
    protected function checkSourceOfFunds(User $user, CgoInvestment $investment): array
    {
        // Check if user has verified source of funds documentation
        $verifiedSources = $user->kycDocuments()
            ->where('document_type', 'source_of_funds')
            ->where('status', 'verified')
            ->where('created_at', '>', now()->subYear())
            ->exists();
            
        if (!$verifiedSources) {
            return ['verified' => false, 'reason' => 'no_source_documentation'];
        }
        
        // Check if declared income supports investment
        $declaredIncome = $user->financial_profile['annual_income'] ?? 0;
        if ($declaredIncome > 0 && $investment->amount > ($declaredIncome * 0.5)) {
            return ['verified' => false, 'reason' => 'investment_exceeds_income_ratio'];
        }
        
        return ['verified' => true];
    }

    /**
     * Create KYC verification request for CGO investment
     */
    public function createVerificationRequest(CgoInvestment $investment, string $level): KycVerification
    {
        $user = $investment->user;
        
        return KycVerification::create([
            'user_id' => $user->id,
            'type' => $level === 'full' ? KycVerification::TYPE_ENHANCED_DUE_DILIGENCE : KycVerification::TYPE_IDENTITY,
            'status' => KycVerification::STATUS_PENDING,
            'provider' => 'internal',
            'verification_data' => [
                'investment_id' => $investment->id,
                'investment_amount' => $investment->amount,
                'required_level' => $level,
                'triggered_by' => 'cgo_investment',
            ],
            'risk_level' => $this->determineRiskLevel($user, $investment),
            'started_at' => now(),
        ]);
    }

    /**
     * Determine risk level for investment
     */
    protected function determineRiskLevel(User $user, CgoInvestment $investment): string
    {
        $riskFactors = 0;
        
        // High investment amount
        if ($investment->amount >= self::FULL_KYC_THRESHOLD) {
            $riskFactors++;
        }
        
        // New user
        if ($user->created_at->gt(now()->subMonths(3))) {
            $riskFactors++;
        }
        
        // High-risk country
        $highRiskCountries = ['AF', 'YE', 'ZW', 'VE']; // Examples
        if (in_array($user->country_code, $highRiskCountries)) {
            $riskFactors += 2;
        }
        
        // PEP status
        if ($user->pep_status) {
            $riskFactors += 2;
        }
        
        // Determine risk level
        if ($riskFactors >= 3) {
            return KycVerification::RISK_LEVEL_HIGH;
        } elseif ($riskFactors >= 1) {
            return KycVerification::RISK_LEVEL_MEDIUM;
        } else {
            return KycVerification::RISK_LEVEL_LOW;
        }
    }
}