<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\Compliance\Services\ComplianceAlertService;
use Exception;
use Workflow\Activity;

class PerformAmlScreeningActivity extends Activity
{
    /**
     * Perform AML (Anti-Money Laundering) screening.
     */
    public function execute(string $agentId, string $agentName, string $countryCode): array
    {
        $alerts = [];
        $hasAlerts = false;
        $riskFactors = [];

        try {
            // Check sanctions lists (OFAC, EU, UN, etc.)
            $sanctionsCheck = $this->checkSanctionsList($agentName, $countryCode);
            if ($sanctionsCheck['isMatch']) {
                $alerts[] = [
                    'type'       => 'sanctions_match',
                    'severity'   => 'critical',
                    'list'       => $sanctionsCheck['list'],
                    'confidence' => $sanctionsCheck['confidence'],
                ];
                $hasAlerts = true;
                $riskFactors[] = 'sanctions_list_match';
            }

            // Check PEP (Politically Exposed Persons) database
            $pepCheck = $this->checkPepDatabase($agentName);
            if ($pepCheck['isPep']) {
                $alerts[] = [
                    'type'     => 'pep_match',
                    'severity' => 'high',
                    'position' => $pepCheck['position'],
                    'country'  => $pepCheck['country'],
                ];
                $hasAlerts = true;
                $riskFactors[] = 'politically_exposed';
            }

            // Check high-risk jurisdictions
            if ($this->isHighRiskJurisdiction($countryCode)) {
                $alerts[] = [
                    'type'     => 'high_risk_jurisdiction',
                    'severity' => 'medium',
                    'country'  => $countryCode,
                    'reason'   => $this->getJurisdictionRiskReason($countryCode),
                ];
                $hasAlerts = true;
                $riskFactors[] = 'high_risk_country';
            }

            // Check adverse media
            $adverseMediaCheck = $this->checkAdverseMedia($agentName);
            if ($adverseMediaCheck['hasAdverseMedia']) {
                $alerts[] = [
                    'type'       => 'adverse_media',
                    'severity'   => 'medium',
                    'sources'    => $adverseMediaCheck['sources'],
                    'categories' => $adverseMediaCheck['categories'],
                ];
                $hasAlerts = true;
                $riskFactors[] = 'negative_news';
            }

            // Create compliance alert if issues found
            if ($hasAlerts) {
                $this->createComplianceAlert($agentId, $alerts);
            }

            return [
                'status'        => $hasAlerts ? 'alerts_found' : 'passed',
                'hasAlerts'     => $hasAlerts,
                'alerts'        => $alerts,
                'riskFactors'   => $riskFactors,
                'screeningDate' => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            logger()->error('AML screening failed', [
                'agent_id' => $agentId,
                'error'    => $e->getMessage(),
            ]);

            // Return conservative result on error
            return [
                'status'    => 'error',
                'hasAlerts' => true,
                'alerts'    => [
                    [
                        'type'     => 'screening_error',
                        'severity' => 'high',
                        'message'  => 'Unable to complete AML screening',
                    ],
                ],
                'riskFactors'   => ['screening_failure'],
                'screeningDate' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Check sanctions lists (simplified implementation).
     */
    private function checkSanctionsList(string $name, string $countryCode): array
    {
        // In production, this would call actual sanctions screening APIs
        // like Dow Jones, Refinitiv, or government APIs

        // Simulate sanctions check
        $sanctionedNames = [
            'John Doe Sanctioned',
            'Evil Corp',
            'Bad Actor Inc',
        ];

        $normalizedName = strtolower(trim($name));
        foreach ($sanctionedNames as $sanctionedName) {
            if (str_contains($normalizedName, strtolower($sanctionedName))) {
                return [
                    'isMatch'    => true,
                    'list'       => 'OFAC SDN',
                    'confidence' => 95,
                ];
            }
        }

        // Check if country is sanctioned
        $sanctionedCountries = ['KP', 'IR', 'SY', 'CU'];
        if (in_array($countryCode, $sanctionedCountries, true)) {
            return [
                'isMatch'    => true,
                'list'       => 'Country Sanctions',
                'confidence' => 100,
            ];
        }

        return [
            'isMatch'    => false,
            'list'       => null,
            'confidence' => 0,
        ];
    }

    /**
     * Check PEP database.
     */
    private function checkPepDatabase(string $name): array
    {
        // In production, this would query actual PEP databases

        // Simulate PEP check
        $pepNames = [
            'Minister Johnson' => ['position' => 'Finance Minister', 'country' => 'UK'],
            'Senator Smith'    => ['position' => 'Senator', 'country' => 'US'],
        ];

        $normalizedName = strtolower(trim($name));
        foreach ($pepNames as $pepName => $details) {
            if (str_contains($normalizedName, strtolower($pepName))) {
                return [
                    'isPep'    => true,
                    'position' => $details['position'],
                    'country'  => $details['country'],
                ];
            }
        }

        return [
            'isPep'    => false,
            'position' => null,
            'country'  => null,
        ];
    }

    /**
     * Check if jurisdiction is high risk.
     */
    private function isHighRiskJurisdiction(string $countryCode): bool
    {
        // FATF grey and black list countries
        $highRiskCountries = [
            'AF', 'AL', 'BS', 'BB', 'BF', 'KH', 'KY', 'CD', 'GH', 'HT',
            'JM', 'JO', 'ML', 'MA', 'MZ', 'MM', 'NI', 'KP', 'PK', 'PA',
            'PH', 'SN', 'SS', 'SY', 'TZ', 'TR', 'UG', 'AE', 'VU', 'YE',
            'ZW',
        ];

        return in_array($countryCode, $highRiskCountries, true);
    }

    /**
     * Get jurisdiction risk reason.
     */
    private function getJurisdictionRiskReason(string $countryCode): string
    {
        $reasons = [
            'KP'      => 'FATF blacklist - financing of proliferation',
            'IR'      => 'FATF blacklist - terrorist financing',
            'AF'      => 'FATF grey list - strategic deficiencies',
            'SY'      => 'War zone and sanctions',
            'default' => 'FATF monitoring - AML/CFT deficiencies',
        ];

        return $reasons[$countryCode] ?? $reasons['default'];
    }

    /**
     * Check adverse media.
     */
    private function checkAdverseMedia(string $name): array
    {
        // In production, this would use news APIs or adverse media screening services

        // Simulate adverse media check
        $adverseMediaTerms = ['fraud', 'scandal', 'investigation', 'lawsuit', 'corruption'];

        $normalizedName = strtolower(trim($name));

        // Simulate finding adverse media for certain patterns
        if (str_contains($normalizedName, 'risky') || str_contains($normalizedName, 'suspect')) {
            return [
                'hasAdverseMedia' => true,
                'sources'         => ['Financial Times', 'Reuters'],
                'categories'      => ['Financial Crime', 'Regulatory Investigation'],
            ];
        }

        return [
            'hasAdverseMedia' => false,
            'sources'         => [],
            'categories'      => [],
        ];
    }

    /**
     * Create compliance alert.
     */
    private function createComplianceAlert(string $agentId, array $alerts): void
    {
        $alertService = app(ComplianceAlertService::class);

        $severity = 'low';
        foreach ($alerts as $alert) {
            if ($alert['severity'] === 'critical') {
                $severity = 'critical';
                break;
            } elseif ($alert['severity'] === 'high') {
                $severity = 'high';
            } elseif ($alert['severity'] === 'medium' && $severity === 'low') {
                $severity = 'medium';
            }
        }

        $alertService->createAlert(
            type: 'aml_screening',
            severity: $severity,
            entityType: 'agent',
            entityId: $agentId,
            description: 'AML screening alerts detected for agent',
            details: [
                'alerts'         => $alerts,
                'screening_date' => now()->toIso8601String(),
            ]
        );
    }
}
