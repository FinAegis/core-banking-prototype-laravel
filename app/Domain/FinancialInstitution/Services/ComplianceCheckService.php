<?php

namespace App\Domain\FinancialInstitution\Services;

use App\Domain\FinancialInstitution\Models\FinancialInstitutionApplication;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ComplianceCheckService
{
    /**
     * Perform comprehensive compliance check.
     */
    public function checkApplication(FinancialInstitutionApplication $application): array
    {
        $results = [
            'aml_check'           => $this->checkAmlCompliance($application),
            'sanctions_check'     => $this->checkSanctions($application),
            'regulatory_check'    => $this->checkRegulatoryStatus($application),
            'certification_check' => $this->checkCertifications($application),
            'jurisdiction_check'  => $this->checkJurisdiction($application),
        ];

        // Calculate overall compliance score
        $totalScore = 0;
        $maxScore = count($results) * 100;

        foreach ($results as $check) {
            $totalScore += $check['score'];
        }

        $results['overall_score'] = round(($totalScore / $maxScore) * 100, 2);
        $results['passed'] = $results['overall_score'] >= 70; // 70% threshold
        $results['checked_at'] = now()->toIso8601String();

        return $results;
    }

    /**
     * Check AML compliance.
     */
    private function checkAmlCompliance(FinancialInstitutionApplication $application): array
    {
        $score = 0;
        $issues = [];
        $recommendations = [];

        // Check if institution has AML program
        if ($application->has_aml_program) {
            $score += 40;
        } else {
            $issues[] = 'No AML program in place';
            $recommendations[] = 'Implement comprehensive AML program';
        }

        // Check if institution has KYC procedures
        if ($application->has_kyc_procedures) {
            $score += 30;
        } else {
            $issues[] = 'No KYC procedures documented';
            $recommendations[] = 'Develop and document KYC procedures';
        }

        // Check primary regulator
        if ($application->primary_regulator) {
            $score += 20;

            // Additional points for strong regulators
            $strongRegulators = ['FCA', 'BaFin', 'FINMA', 'OCC', 'ECB'];
            if (in_array($application->primary_regulator, $strongRegulators)) {
                $score += 10;
            }
        } else {
            $issues[] = 'No primary regulator specified';
        }

        return [
            'score'           => min($score, 100),
            'passed'          => $score >= 70,
            'issues'          => $issues,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Check sanctions lists.
     */
    private function checkSanctions(FinancialInstitutionApplication $application): array
    {
        $score = 100; // Start with perfect score
        $issues = [];
        $matches = [];

        // Check against sanctions lists (simplified)
        $sanctionedCountries = ['IR', 'KP', 'SY', 'CU', 'VE'];

        if (in_array($application->country, $sanctionedCountries)) {
            $score = 0;
            $issues[] = 'Institution based in sanctioned country';
            $matches[] = [
                'list'  => 'Country Sanctions',
                'match' => $application->country,
            ];
        }

        // Check if any target markets are sanctioned
        $targetMarkets = $application->target_markets ?? [];
        $sanctionedMarkets = array_intersect($targetMarkets, $sanctionedCountries);

        if (! empty($sanctionedMarkets)) {
            $score -= 30;
            $issues[] = 'Target markets include sanctioned countries';
            foreach ($sanctionedMarkets as $market) {
                $matches[] = [
                    'list'  => 'Target Market Sanctions',
                    'match' => $market,
                ];
            }
        }

        // In production, would check against actual sanctions databases:
        // - OFAC SDN List
        // - EU Consolidated List
        // - UN Sanctions List
        // - UK HM Treasury List

        return [
            'score'   => max($score, 0),
            'passed'  => $score >= 50,
            'issues'  => $issues,
            'matches' => $matches,
        ];
    }

    /**
     * Check regulatory status.
     */
    private function checkRegulatoryStatus(FinancialInstitutionApplication $application): array
    {
        $score = 0;
        $issues = [];
        $validations = [];

        // Check if institution has regulatory license
        if ($application->regulatory_license_number) {
            $score += 50;

            // In production, would verify license with regulatory body
            $validations[] = [
                'type'      => 'Regulatory License',
                'number'    => $application->regulatory_license_number,
                'regulator' => $application->primary_regulator,
                'verified'  => true, // Would be actual verification result
            ];
        } else {
            $issues[] = 'No regulatory license number provided';
        }

        // Check years in operation
        if ($application->years_in_operation >= 5) {
            $score += 30;
        } elseif ($application->years_in_operation >= 3) {
            $score += 20;
        } elseif ($application->years_in_operation >= 1) {
            $score += 10;
        } else {
            $issues[] = 'Institution operating for less than 1 year';
        }

        // Check registration number validity (simplified)
        if ($application->registration_number) {
            $score += 20;
            $validations[] = [
                'type'     => 'Company Registration',
                'number'   => $application->registration_number,
                'country'  => $application->country,
                'verified' => true, // Would check with company registry
            ];
        }

        return [
            'score'       => min($score, 100),
            'passed'      => $score >= 50,
            'issues'      => $issues,
            'validations' => $validations,
        ];
    }

    /**
     * Check certifications.
     */
    private function checkCertifications(FinancialInstitutionApplication $application): array
    {
        $score = 50; // Base score
        $certifications = [];
        $missing = [];

        // Check compliance certifications
        $complianceCerts = $application->compliance_certifications ?? [];

        // Check for important certifications
        $importantCerts = [
            'ISO27001' => 'Information Security Management',
            'SOC2'     => 'Service Organization Control 2',
            'PCI-DSS'  => 'Payment Card Industry Data Security Standard',
            'ISO9001'  => 'Quality Management',
        ];

        foreach ($importantCerts as $cert => $name) {
            if (in_array($cert, $complianceCerts)) {
                $score += 10;
                $certifications[] = [
                    'type'   => $cert,
                    'name'   => $name,
                    'status' => 'present',
                ];
            } else {
                $missing[] = $cert;
            }
        }

        // Type-specific requirements
        if ($application->institution_type === 'payment_processor' && ! $application->is_pci_compliant) {
            $score -= 20;
            $missing[] = 'PCI compliance required for payment processors';
        }

        // GDPR compliance for EU operations
        $euCountries = ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'SE', 'DK', 'FI', 'IE', 'PT', 'PL', 'CZ', 'RO', 'GR', 'HU', 'BG', 'HR', 'SI', 'SK', 'LT', 'LV', 'EE', 'CY', 'LU', 'MT'];
        if (in_array($application->country, $euCountries) || array_intersect($application->target_markets ?? [], $euCountries)) {
            if ($application->is_gdpr_compliant) {
                $score += 10;
                $certifications[] = [
                    'type'   => 'GDPR',
                    'name'   => 'General Data Protection Regulation',
                    'status' => 'compliant',
                ];
            } else {
                $score -= 10;
                $missing[] = 'GDPR compliance required for EU operations';
            }
        }

        return [
            'score'          => min(max($score, 0), 100),
            'passed'         => $score >= 50,
            'certifications' => $certifications,
            'missing'        => $missing,
        ];
    }

    /**
     * Check jurisdiction compatibility.
     */
    private function checkJurisdiction(FinancialInstitutionApplication $application): array
    {
        $score = 100;
        $issues = [];
        $compatible = [];
        $incompatible = [];

        // Define jurisdiction tiers
        $tier1 = ['US', 'GB', 'DE', 'FR', 'CH', 'NL', 'SE', 'NO', 'DK', 'FI', 'AU', 'CA', 'JP', 'SG'];
        $tier2 = ['ES', 'IT', 'BE', 'AT', 'IE', 'LU', 'PT', 'NZ', 'HK'];
        $tier3 = ['PL', 'CZ', 'HU', 'RO', 'BG', 'HR', 'SI', 'SK', 'LT', 'LV', 'EE', 'MT', 'CY'];

        // Check base country
        if (in_array($application->country, $tier1)) {
            $compatible[] = 'Tier 1 jurisdiction';
        } elseif (in_array($application->country, $tier2)) {
            $score -= 10;
            $compatible[] = 'Tier 2 jurisdiction';
        } elseif (in_array($application->country, $tier3)) {
            $score -= 20;
            $compatible[] = 'Tier 3 jurisdiction';
        } else {
            $score -= 40;
            $issues[] = 'Jurisdiction requires enhanced due diligence';
        }

        // Check target markets
        $targetMarkets = $application->target_markets ?? [];
        $restrictedMarkets = ['AF', 'YE', 'MM', 'LA', 'UG', 'KH'];

        $problematicMarkets = array_intersect($targetMarkets, $restrictedMarkets);
        if (! empty($problematicMarkets)) {
            $score -= 20;
            $incompatible = array_merge($incompatible, $problematicMarkets);
            $issues[] = 'Target markets include high-risk jurisdictions';
        }

        return [
            'score'                      => max($score, 0),
            'passed'                     => $score >= 60,
            'issues'                     => $issues,
            'compatible_jurisdictions'   => $compatible,
            'incompatible_jurisdictions' => $incompatible,
        ];
    }

    /**
     * Check with external compliance service (stub).
     */
    private function checkExternalCompliance(FinancialInstitutionApplication $application): ?array
    {
        try {
            // In production, would integrate with services like:
            // - Refinitiv World-Check
            // - Dow Jones Risk & Compliance
            // - LexisNexis Risk Solutions

            // Example API call (disabled)
            /*
            $response = Http::timeout(10)
                ->withHeaders(['Authorization' => 'Bearer ' . config('services.compliance.api_key')])
                ->post('https://api.compliance-service.com/check', [
                    'company_name' => $application->legal_name,
                    'country' => $application->country,
                    'registration_number' => $application->registration_number,
                ]);

            if ($response->successful()) {
                return $response->json();
            }
            */

            return null;
        } catch (Exception $e) {
            Log::error(
                'External compliance check failed',
                [
                    'application_id' => $application->id,
                    'error'          => $e->getMessage(),
                ]
            );

            return null;
        }
    }
}
