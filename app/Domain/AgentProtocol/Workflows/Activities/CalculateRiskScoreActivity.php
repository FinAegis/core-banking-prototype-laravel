<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use Workflow\Activity;

class CalculateRiskScoreActivity extends Activity
{
    /**
     * Calculate risk score based on verification results and AML alerts.
     */
    public function execute(
        string $agentId,
        array $verificationResults,
        array $amlAlerts,
        string $countryCode
    ): array {
        $riskScore = 0;
        $riskFactors = [];
        $weights = $this->getRiskWeights();

        // Country risk scoring
        $countryRisk = $this->getCountryRiskScore($countryCode);
        $riskScore += $countryRisk * $weights['country'];
        if ($countryRisk > 50) {
            $riskFactors[] = 'high_risk_country';
        }

        // AML alert scoring
        if (! empty($amlAlerts)) {
            foreach ($amlAlerts as $alert) {
                switch ($alert['severity']) {
                    case 'critical':
                        $riskScore += 40 * $weights['aml'];
                        $riskFactors[] = 'critical_aml_alert';
                        break;
                    case 'high':
                        $riskScore += 25 * $weights['aml'];
                        $riskFactors[] = 'high_aml_alert';
                        break;
                    case 'medium':
                        $riskScore += 15 * $weights['aml'];
                        $riskFactors[] = 'medium_aml_alert';
                        break;
                    default:
                        $riskScore += 5 * $weights['aml'];
                        break;
                }
            }
        }

        // Identity verification scoring
        if (isset($verificationResults['identity'])) {
            $identityScore = $this->scoreIdentityVerification($verificationResults['identity']);
            $riskScore += (100 - $identityScore) * $weights['identity'];

            if ($identityScore < 70) {
                $riskFactors[] = 'weak_identity_verification';
            }
        }

        // Address verification scoring
        if (isset($verificationResults['address'])) {
            $addressScore = $this->scoreAddressVerification($verificationResults['address']);
            $riskScore += (100 - $addressScore) * $weights['address'];

            if ($addressScore < 80) {
                $riskFactors[] = 'address_verification_issues';
            }
        }

        // Biometric verification scoring
        if (isset($verificationResults['biometric'])) {
            $biometricScore = $this->scoreBiometricVerification($verificationResults['biometric']);
            $riskScore += (100 - $biometricScore) * $weights['biometric'];

            if ($biometricScore < 85) {
                $riskFactors[] = 'biometric_mismatch';
            }
        }

        // Business verification scoring (for business agents)
        if (isset($verificationResults['business'])) {
            $businessScore = $this->scoreBusinessVerification($verificationResults['business']);
            $riskScore += (100 - $businessScore) * $weights['business'];

            if ($businessScore < 70) {
                $riskFactors[] = 'business_verification_concerns';
            }

            // Additional risk for high-risk business types
            if ($verificationResults['business']['isHighRisk'] ?? false) {
                $riskScore += 20;
                $riskFactors[] = 'high_risk_business_type';
            }
        }

        // Behavioral risk factors (placeholder for future implementation)
        $behavioralRisk = $this->calculateBehavioralRisk($agentId);
        $riskScore += $behavioralRisk * $weights['behavioral'];

        // Normalize score to 0-100 range
        $riskScore = min(100, max(0, round($riskScore)));

        // Apply risk score adjustments based on patterns
        $riskScore = $this->applyRiskAdjustments($riskScore, $riskFactors);

        return [
            'score'          => (int) $riskScore,
            'factors'        => $riskFactors,
            'category'       => $this->getRiskCategory($riskScore),
            'recommendation' => $this->getRiskRecommendation($riskScore, $riskFactors),
            'calculatedAt'   => now()->toIso8601String(),
        ];
    }

    /**
     * Get risk scoring weights.
     */
    private function getRiskWeights(): array
    {
        return [
            'country'    => 0.20,
            'aml'        => 0.30,
            'identity'   => 0.20,
            'address'    => 0.10,
            'biometric'  => 0.10,
            'business'   => 0.15,
            'behavioral' => 0.05,
        ];
    }

    /**
     * Get country risk score.
     */
    private function getCountryRiskScore(string $countryCode): float
    {
        $riskScores = [
            // Low risk countries
            'US' => 10, 'GB' => 10, 'DE' => 10, 'FR' => 10, 'JP' => 10,
            'CA' => 10, 'AU' => 10, 'NZ' => 10, 'CH' => 5, 'NO' => 5,

            // Medium risk countries
            'BR' => 30, 'IN' => 30, 'CN' => 35, 'RU' => 40, 'MX' => 35,
            'ID' => 35, 'TH' => 30, 'MY' => 25, 'PH' => 35, 'VN' => 35,

            // High risk countries
            'NG' => 60, 'PK' => 65, 'BD' => 55, 'KE' => 50, 'GH' => 50,
            'UG' => 55, 'ZW' => 70, 'VE' => 75, 'LB' => 60, 'IQ' => 70,

            // Very high risk countries
            'AF' => 90, 'SY' => 95, 'YE' => 90, 'LY' => 85, 'SO' => 95,
            'KP' => 100, 'IR' => 95, 'CU' => 80, 'SD' => 85, 'MM' => 80,
        ];

        return $riskScores[$countryCode] ?? 50; // Default medium risk
    }

    /**
     * Score identity verification.
     */
    private function scoreIdentityVerification(array $result): float
    {
        $score = 100;

        if ($result['status'] !== 'verified' && $result['status'] !== 'passed') {
            $score -= 50;
        }

        $confidence = $result['confidence'] ?? 0;
        if ($confidence < 90) {
            $score -= (90 - $confidence) * 0.5;
        }

        if ($result['documentExpired'] ?? false) {
            $score -= 30;
        }

        if ($result['documentTampered'] ?? false) {
            $score -= 50;
        }

        return max(0, $score);
    }

    /**
     * Score address verification.
     */
    private function scoreAddressVerification(array $result): float
    {
        $score = 100;

        if ($result['status'] !== 'verified' && $result['status'] !== 'passed') {
            $score -= 40;
        }

        if (($result['addressMatch'] ?? false) === false) {
            $score -= 30;
        }

        $documentAge = $result['documentAge'] ?? 0;
        if ($documentAge > 90) { // Document older than 90 days
            $score -= 20;
        }

        return max(0, $score);
    }

    /**
     * Score biometric verification.
     */
    private function scoreBiometricVerification(array $result): float
    {
        $score = 100;

        if ($result['status'] !== 'matched') {
            $score -= 60;
        }

        $matchScore = $result['matchScore'] ?? 0;
        if ($matchScore < 95) {
            $score -= (95 - $matchScore);
        }

        if (($result['livenessCheck'] ?? false) === false) {
            $score -= 40;
        }

        return max(0, $score);
    }

    /**
     * Score business verification.
     */
    private function scoreBusinessVerification(array $result): float
    {
        $score = 100;

        if ($result['status'] !== 'verified') {
            $score -= 50;
        }

        if (($result['registrationValid'] ?? false) === false) {
            $score -= 40;
        }

        if (($result['taxCompliant'] ?? false) === false) {
            $score -= 30;
        }

        $yearsInBusiness = $result['yearsInBusiness'] ?? 0;
        if ($yearsInBusiness < 2) {
            $score -= 20;
        }

        return max(0, $score);
    }

    /**
     * Calculate behavioral risk (placeholder).
     */
    private function calculateBehavioralRisk(string $agentId): float
    {
        // In production, this would analyze:
        // - Transaction patterns
        // - Account age
        // - Previous compliance issues
        // - Velocity changes
        // - Geographic anomalies

        return 0; // No behavioral data for new agents
    }

    /**
     * Apply risk adjustments based on patterns.
     */
    private function applyRiskAdjustments(float $riskScore, array $riskFactors): float
    {
        // Increase risk for multiple critical factors
        $criticalFactors = array_intersect($riskFactors, [
            'critical_aml_alert',
            'sanctions_list_match',
            'high_risk_business_type',
        ]);

        if (count($criticalFactors) > 1) {
            $riskScore = min(100, $riskScore * 1.2);
        }

        // Decrease risk for strong verification
        $strongFactors = array_diff($riskFactors, [
            'weak_identity_verification',
            'address_verification_issues',
            'biometric_mismatch',
            'business_verification_concerns',
        ]);

        if (empty($riskFactors) || count($strongFactors) === count($riskFactors)) {
            $riskScore = max(0, $riskScore * 0.9);
        }

        return round($riskScore);
    }

    /**
     * Get risk category.
     */
    private function getRiskCategory(float $riskScore): string
    {
        return match (true) {
            $riskScore <= 20 => 'low',
            $riskScore <= 40 => 'medium-low',
            $riskScore <= 60 => 'medium',
            $riskScore <= 80 => 'medium-high',
            default          => 'high',
        };
    }

    /**
     * Get risk recommendation.
     */
    private function getRiskRecommendation(float $riskScore, array $riskFactors): string
    {
        if ($riskScore > 80) {
            return 'Reject or require enhanced due diligence with senior approval';
        }

        if ($riskScore > 60) {
            return 'Require enhanced monitoring and periodic review';
        }

        if ($riskScore > 40) {
            return 'Standard monitoring with quarterly reviews';
        }

        if (! empty($riskFactors)) {
            return 'Approve with standard monitoring';
        }

        return 'Approve with minimal monitoring requirements';
    }
}
