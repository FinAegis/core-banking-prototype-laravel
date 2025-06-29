<?php

namespace App\Domain\FinancialInstitution\Services;

use App\Models\FinancialInstitutionApplication;

class RiskAssessmentService
{
    /**
     * Perform comprehensive risk assessment
     */
    public function assessApplication(FinancialInstitutionApplication $application): array
    {
        $assessment = [
            'geographic_risk' => $this->assessGeographicRisk($application),
            'business_model_risk' => $this->assessBusinessModelRisk($application),
            'volume_risk' => $this->assessVolumeRisk($application),
            'regulatory_risk' => $this->assessRegulatoryRisk($application),
            'financial_risk' => $this->assessFinancialRisk($application),
            'operational_risk' => $this->assessOperationalRisk($application),
        ];
        
        // Calculate weighted risk score
        $weights = [
            'geographic_risk' => 0.25,
            'business_model_risk' => 0.20,
            'volume_risk' => 0.15,
            'regulatory_risk' => 0.20,
            'financial_risk' => 0.10,
            'operational_risk' => 0.10,
        ];
        
        $totalRisk = 0;
        foreach ($assessment as $category => $result) {
            $totalRisk += $result['score'] * $weights[$category];
        }
        
        $assessment['total_risk_score'] = round($totalRisk, 2);
        $assessment['risk_rating'] = $this->getRiskRating($totalRisk);
        $assessment['assessed_at'] = now()->toIso8601String();
        
        return $assessment;
    }
    
    /**
     * Assess geographic risk
     */
    private function assessGeographicRisk(FinancialInstitutionApplication $application): array
    {
        $riskScore = 0;
        $factors = [];
        
        // Country risk categories
        $highRiskCountries = ['AF', 'IR', 'KP', 'MM', 'SY', 'YE', 'SO', 'LY', 'VE'];
        $mediumRiskCountries = ['RU', 'UA', 'BY', 'TR', 'EG', 'NG', 'KE', 'PK', 'BD'];
        $lowRiskCountries = ['US', 'GB', 'DE', 'FR', 'CH', 'NL', 'SE', 'NO', 'DK', 'FI', 'AU', 'CA', 'JP', 'SG'];
        
        // Base country risk
        if (in_array($application->country, $highRiskCountries)) {
            $riskScore += 80;
            $factors[] = 'High-risk jurisdiction';
        } elseif (in_array($application->country, $mediumRiskCountries)) {
            $riskScore += 50;
            $factors[] = 'Medium-risk jurisdiction';
        } elseif (in_array($application->country, $lowRiskCountries)) {
            $riskScore += 10;
            $factors[] = 'Low-risk jurisdiction';
        } else {
            $riskScore += 30;
            $factors[] = 'Standard-risk jurisdiction';
        }
        
        // Target markets risk
        $targetMarkets = $application->target_markets ?? [];
        $highRiskMarkets = array_intersect($targetMarkets, $highRiskCountries);
        
        if (count($highRiskMarkets) > 0) {
            $riskScore += min(20 * count($highRiskMarkets), 40);
            $factors[] = 'Operations in high-risk markets';
        }
        
        // Cross-border operations
        if (count($targetMarkets) > 10) {
            $riskScore += 10;
            $factors[] = 'Extensive cross-border operations';
        }
        
        return [
            'score' => min($riskScore, 100),
            'factors' => $factors,
            'high_risk_exposures' => $highRiskMarkets,
        ];
    }
    
    /**
     * Assess business model risk
     */
    private function assessBusinessModelRisk(FinancialInstitutionApplication $application): array
    {
        $riskScore = 0;
        $factors = [];
        
        // Institution type risk
        $typeRisks = [
            'bank' => 20,
            'credit_union' => 15,
            'investment_firm' => 30,
            'payment_processor' => 40,
            'fintech' => 45,
            'emi' => 35,
            'broker_dealer' => 30,
            'insurance' => 20,
            'other' => 50,
        ];
        
        $riskScore += $typeRisks[$application->institution_type] ?? 50;
        $factors[] = ucfirst($application->institution_type) . ' business model';
        
        // Product complexity
        $products = $application->product_offerings ?? [];
        $highRiskProducts = ['crypto', 'derivatives', 'forex', 'binary_options', 'crowdfunding'];
        $riskyProducts = array_intersect($products, $highRiskProducts);
        
        if (!empty($riskyProducts)) {
            $riskScore += min(15 * count($riskyProducts), 30);
            $factors[] = 'High-risk product offerings';
        }
        
        // Customer base
        if (in_array('retail', $products)) {
            $riskScore += 10;
            $factors[] = 'Retail customer exposure';
        }
        
        if (in_array('corporate', $products)) {
            $riskScore += 5;
            $factors[] = 'Corporate customer base';
        }
        
        return [
            'score' => min($riskScore, 100),
            'factors' => $factors,
            'risky_products' => $riskyProducts,
        ];
    }
    
    /**
     * Assess volume risk
     */
    private function assessVolumeRisk(FinancialInstitutionApplication $application): array
    {
        $riskScore = 0;
        $factors = [];
        
        $monthlyVolume = $application->expected_monthly_volume ?? 0;
        $monthlyTransactions = $application->expected_monthly_transactions ?? 0;
        
        // Volume-based risk
        if ($monthlyVolume > 100000000) { // > $100M
            $riskScore += 40;
            $factors[] = 'Very high transaction volume';
        } elseif ($monthlyVolume > 10000000) { // > $10M
            $riskScore += 25;
            $factors[] = 'High transaction volume';
        } elseif ($monthlyVolume > 1000000) { // > $1M
            $riskScore += 15;
            $factors[] = 'Moderate transaction volume';
        } else {
            $riskScore += 5;
            $factors[] = 'Low transaction volume';
        }
        
        // Transaction count risk
        if ($monthlyTransactions > 1000000) {
            $riskScore += 30;
            $factors[] = 'Very high transaction count';
        } elseif ($monthlyTransactions > 100000) {
            $riskScore += 20;
            $factors[] = 'High transaction count';
        } elseif ($monthlyTransactions > 10000) {
            $riskScore += 10;
            $factors[] = 'Moderate transaction count';
        }
        
        // Average transaction size
        if ($monthlyTransactions > 0) {
            $avgTransaction = $monthlyVolume / $monthlyTransactions;
            if ($avgTransaction > 10000) {
                $riskScore += 20;
                $factors[] = 'High average transaction value';
            } elseif ($avgTransaction < 10) {
                $riskScore += 15;
                $factors[] = 'Micro-transaction pattern';
            }
        }
        
        return [
            'score' => min($riskScore, 100),
            'factors' => $factors,
            'monthly_volume' => $monthlyVolume,
            'monthly_transactions' => $monthlyTransactions,
        ];
    }
    
    /**
     * Assess regulatory risk
     */
    private function assessRegulatoryRisk(FinancialInstitutionApplication $application): array
    {
        $riskScore = 0;
        $factors = [];
        
        // No primary regulator
        if (!$application->primary_regulator) {
            $riskScore += 40;
            $factors[] = 'No primary regulator identified';
        } else {
            // Assess regulator strength
            $strongRegulators = ['FCA', 'BaFin', 'FINMA', 'OCC', 'ECB', 'MAS', 'APRA'];
            if (!in_array($application->primary_regulator, $strongRegulators)) {
                $riskScore += 20;
                $factors[] = 'Regulator with limited international recognition';
            }
        }
        
        // Compliance gaps
        if (!$application->has_aml_program) {
            $riskScore += 25;
            $factors[] = 'No AML program';
        }
        
        if (!$application->has_kyc_procedures) {
            $riskScore += 20;
            $factors[] = 'No KYC procedures';
        }
        
        if (!$application->has_data_protection_policy) {
            $riskScore += 15;
            $factors[] = 'No data protection policy';
        }
        
        // Years in operation
        if ($application->years_in_operation < 3) {
            $riskScore += 15;
            $factors[] = 'Limited operating history';
        }
        
        return [
            'score' => min($riskScore, 100),
            'factors' => $factors,
            'compliance_gaps' => array_filter([
                !$application->has_aml_program ? 'AML' : null,
                !$application->has_kyc_procedures ? 'KYC' : null,
                !$application->has_data_protection_policy ? 'Data Protection' : null,
            ]),
        ];
    }
    
    /**
     * Assess financial risk
     */
    private function assessFinancialRisk(FinancialInstitutionApplication $application): array
    {
        $riskScore = 0;
        $factors = [];
        
        $aum = $application->assets_under_management ?? 0;
        
        // AUM-based risk
        if ($aum < 1000000) { // < $1M
            $riskScore += 40;
            $factors[] = 'Very low assets under management';
        } elseif ($aum < 10000000) { // < $10M
            $riskScore += 25;
            $factors[] = 'Low assets under management';
        } elseif ($aum < 100000000) { // < $100M
            $riskScore += 15;
            $factors[] = 'Moderate assets under management';
        } else {
            $riskScore += 5;
            $factors[] = 'Substantial assets under management';
        }
        
        // Institution type financial risk
        if (in_array($application->institution_type, ['fintech', 'emi', 'payment_processor'])) {
            $riskScore += 20;
            $factors[] = 'Non-traditional financial institution';
        }
        
        return [
            'score' => min($riskScore, 100),
            'factors' => $factors,
            'assets_under_management' => $aum,
        ];
    }
    
    /**
     * Assess operational risk
     */
    private function assessOperationalRisk(FinancialInstitutionApplication $application): array
    {
        $riskScore = 0;
        $factors = [];
        
        // Technical requirements
        $techRequirements = $application->integration_requirements ?? [];
        
        if (in_array('real_time_processing', $techRequirements)) {
            $riskScore += 15;
            $factors[] = 'Real-time processing requirements';
        }
        
        if (in_array('high_availability', $techRequirements)) {
            $riskScore += 10;
            $factors[] = 'High availability requirements';
        }
        
        // Security certifications
        $secCerts = $application->security_certifications ?? [];
        if (empty($secCerts)) {
            $riskScore += 30;
            $factors[] = 'No security certifications';
        } elseif (!in_array('ISO27001', $secCerts) && !in_array('SOC2', $secCerts)) {
            $riskScore += 15;
            $factors[] = 'Limited security certifications';
        }
        
        // Currency complexity
        $currencies = $application->required_currencies ?? [];
        if (count($currencies) > 10) {
            $riskScore += 20;
            $factors[] = 'High currency complexity';
        }
        
        // PCI compliance for payment processors
        if ($application->institution_type === 'payment_processor' && !$application->is_pci_compliant) {
            $riskScore += 25;
            $factors[] = 'Payment processor without PCI compliance';
        }
        
        return [
            'score' => min($riskScore, 100),
            'factors' => $factors,
        ];
    }
    
    /**
     * Get risk rating from score
     */
    private function getRiskRating(float $score): string
    {
        if ($score <= 30) {
            return 'low';
        } elseif ($score <= 60) {
            return 'medium';
        } else {
            return 'high';
        }
    }
}