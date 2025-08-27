<?php

namespace App\Domain\FinancialInstitution\Services;

use App\Domain\FinancialInstitution\Events\ApplicationApproved;
use App\Domain\FinancialInstitution\Events\ApplicationRejected;
use App\Domain\FinancialInstitution\Events\ApplicationSubmitted;
use App\Domain\FinancialInstitution\Events\PartnerActivated;
use App\Domain\FinancialInstitution\Exceptions\OnboardingException;
use App\Domain\FinancialInstitution\Models\FinancialInstitutionApplication;
use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingService
{
    private DocumentVerificationService $documentService;

    private ComplianceCheckService $complianceService;

    private RiskAssessmentService $riskService;

    public function __construct(
        DocumentVerificationService $documentService,
        ComplianceCheckService $complianceService,
        RiskAssessmentService $riskService
    ) {
        $this->documentService = $documentService;
        $this->complianceService = $complianceService;
        $this->riskService = $riskService;
    }

    /**
     * Submit a new application.
     */
    public function submitApplication(array $data): FinancialInstitutionApplication
    {
        DB::beginTransaction();

        try {
            // Create application
            $application = FinancialInstitutionApplication::create($data);

            // Set required documents
            $application->required_documents = $application->getRequiredDocuments();

            // Calculate initial risk score
            $application->calculateRiskScore();
            $application->save();

            // Dispatch event
            event(new ApplicationSubmitted($application));

            DB::commit();

            Log::info(
                'Financial institution application submitted',
                [
                    'application_id'   => $application->id,
                    'institution_name' => $application->institution_name,
                ]
            );

            return $application;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error(
                'Failed to submit financial institution application',
                [
                    'error' => $e->getMessage(),
                    'data'  => $data,
                ]
            );
            throw new OnboardingException('Failed to submit application: ' . $e->getMessage());
        }
    }

    /**
     * Start application review process.
     */
    public function startReview(FinancialInstitutionApplication $application, string $reviewerId): void
    {
        if (! $application->isReviewable()) {
            throw new OnboardingException('Application is not in a reviewable state');
        }

        $application->update(
            [
                'status'       => FinancialInstitutionApplication::STATUS_UNDER_REVIEW,
                'review_stage' => FinancialInstitutionApplication::STAGE_INITIAL,
                'reviewed_by'  => $reviewerId,
                'reviewed_at'  => now(),
            ]
        );

        Log::info(
            'Application review started',
            [
                'application_id' => $application->id,
                'reviewer_id'    => $reviewerId,
            ]
        );
    }

    /**
     * Perform compliance checks.
     */
    public function performComplianceCheck(FinancialInstitutionApplication $application): array
    {
        $application->update(
            [
                'review_stage' => FinancialInstitutionApplication::STAGE_COMPLIANCE,
            ]
        );

        $results = $this->complianceService->checkApplication($application);

        // Update application with compliance results
        $application->update(
            [
                'metadata' => array_merge(
                    $application->metadata ?? [],
                    [
                        'compliance_check'      => $results,
                        'compliance_checked_at' => now()->toIso8601String(),
                    ]
                ),
            ]
        );

        Log::info(
            'Compliance check completed',
            [
                'application_id' => $application->id,
                'passed'         => $results['passed'],
            ]
        );

        return $results;
    }

    /**
     * Perform technical assessment.
     */
    public function performTechnicalAssessment(FinancialInstitutionApplication $application): array
    {
        $application->update(
            [
                'review_stage' => FinancialInstitutionApplication::STAGE_TECHNICAL,
            ]
        );

        $assessment = [
            'api_integration' => $this->assessApiCapabilities($application),
            'security'        => $this->assessSecurityMeasures($application),
            'scalability'     => $this->assessScalability($application),
            'passed'          => true,
        ];

        // Determine if technical assessment passes
        foreach (['api_integration', 'security', 'scalability'] as $criteria) {
            if (! $assessment[$criteria]['passed']) {
                $assessment['passed'] = false;
                break;
            }
        }

        // Update application
        $application->update(
            [
                'metadata' => array_merge(
                    $application->metadata ?? [],
                    [
                        'technical_assessment'  => $assessment,
                        'technical_assessed_at' => now()->toIso8601String(),
                    ]
                ),
            ]
        );

        return $assessment;
    }

    /**
     * Approve application and create partner.
     */
    public function approveApplication(
        FinancialInstitutionApplication $application,
        array $partnerConfig = []
    ): FinancialInstitutionPartner {
        if (! $application->isReviewable()) {
            throw new OnboardingException('Application is not in a reviewable state');
        }

        DB::beginTransaction();

        try {
            // Update application status
            $application->update(
                [
                    'status'       => FinancialInstitutionApplication::STATUS_APPROVED,
                    'review_stage' => FinancialInstitutionApplication::STAGE_FINAL,
                ]
            );

            // Create partner
            $partner = $this->createPartner($application, $partnerConfig);

            // Update application with partner reference
            $application->update(
                [
                    'partner_id'              => $partner->id,
                    'onboarding_completed_at' => now(),
                ]
            );

            // Dispatch event
            event(new ApplicationApproved($application, $partner));
            event(new PartnerActivated($partner));

            DB::commit();

            Log::info(
                'Application approved and partner created',
                [
                    'application_id' => $application->id,
                    'partner_id'     => $partner->id,
                ]
            );

            return $partner;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error(
                'Failed to approve application',
                [
                    'application_id' => $application->id,
                    'error'          => $e->getMessage(),
                ]
            );
            throw new OnboardingException('Failed to approve application: ' . $e->getMessage());
        }
    }

    /**
     * Reject application.
     */
    public function rejectApplication(
        FinancialInstitutionApplication $application,
        string $reason
    ): void {
        if (! $application->isReviewable()) {
            throw new OnboardingException('Application is not in a reviewable state');
        }

        $application->update(
            [
                'status'           => FinancialInstitutionApplication::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]
        );

        event(new ApplicationRejected($application, $reason));

        Log::info(
            'Application rejected',
            [
                'application_id' => $application->id,
                'reason'         => $reason,
            ]
        );
    }

    /**
     * Put application on hold.
     */
    public function putOnHold(
        FinancialInstitutionApplication $application,
        string $reason
    ): void {
        $application->update(
            [
                'status'       => FinancialInstitutionApplication::STATUS_ON_HOLD,
                'review_notes' => $reason,
            ]
        );

        Log::info(
            'Application put on hold',
            [
                'application_id' => $application->id,
                'reason'         => $reason,
            ]
        );
    }

    /**
     * Create partner from approved application.
     */
    private function createPartner(
        FinancialInstitutionApplication $application,
        array $config
    ): FinancialInstitutionPartner {
        $defaultConfig = [
            'application_id'   => $application->id,
            'institution_name' => $application->institution_name,
            'legal_name'       => $application->legal_name,
            'institution_type' => $application->institution_type,
            'country'          => $application->country,
            'status'           => FinancialInstitutionPartner::STATUS_ACTIVE,
            'risk_rating'      => $application->risk_rating,
            'risk_score'       => $application->risk_score,
            'primary_contact'  => [
                'name'     => $application->contact_name,
                'email'    => $application->contact_email,
                'phone'    => $application->contact_phone,
                'position' => $application->contact_position,
            ],
            'fee_structure'      => $this->getDefaultFeeStructure($application),
            'allowed_currencies' => $application->required_currencies,
            'api_permissions'    => $this->getDefaultApiPermissions($application),
            'enabled_features'   => $this->getDefaultEnabledFeatures($application),
            'activated_at'       => now(),
        ];

        $partnerData = array_merge($defaultConfig, $config);

        return FinancialInstitutionPartner::create($partnerData);
    }

    /**
     * Get default fee structure based on institution type.
     */
    private function getDefaultFeeStructure(FinancialInstitutionApplication $application): array
    {
        return [
            'account_creation'       => 0,
            'monthly_account'        => 0,
            'transaction_percentage' => match ($application->institution_type) {
                'bank'              => 0.1,
                'credit_union'      => 0.05,
                'fintech'           => 0.15,
                'payment_processor' => 0.2,
                default             => 0.1,
            },
            'minimum_monthly_fee' => match ($application->institution_type) {
                'bank'              => 1000,
                'credit_union'      => 500,
                'fintech'           => 2000,
                'payment_processor' => 2500,
                default             => 1000,
            },
        ];
    }

    /**
     * Get default API permissions.
     */
    private function getDefaultApiPermissions(FinancialInstitutionApplication $application): array
    {
        $basePermissions = [
            'accounts.read',
            'accounts.create',
            'transactions.read',
            'transactions.create',
            'users.read',
            'webhooks.manage',
        ];

        // Add type-specific permissions
        if (in_array($application->institution_type, ['bank', 'credit_union'])) {
            $basePermissions[] = 'accounts.update';
            $basePermissions[] = 'accounts.close';
        }

        if ($application->institution_type === 'payment_processor') {
            $basePermissions[] = 'payments.process';
            $basePermissions[] = 'refunds.create';
        }

        return $basePermissions;
    }

    /**
     * Get default enabled features.
     */
    private function getDefaultEnabledFeatures(FinancialInstitutionApplication $application): array
    {
        $features = [
            'account_management',
            'transaction_history',
            'balance_inquiries',
            'webhooks',
            'reporting',
        ];

        if ($application->requires_api_access) {
            $features[] = 'api_access';
        }

        if (in_array($application->institution_type, ['bank', 'payment_processor'])) {
            $features[] = 'transfers';
            $features[] = 'bulk_operations';
        }

        return $features;
    }

    /**
     * Assess API capabilities.
     */
    private function assessApiCapabilities(FinancialInstitutionApplication $application): array
    {
        $score = 0;
        $requirements = [];

        if ($application->requires_api_access) {
            $requirements[] = 'REST API support';
            $score += 25;
        }

        if ($application->requires_webhooks) {
            $requirements[] = 'Webhook implementation';
            $score += 25;
        }

        if (in_array('real_time_processing', $application->integration_requirements ?? [])) {
            $requirements[] = 'Real-time processing capability';
            $score += 25;
        }

        if (in_array('batch_processing', $application->integration_requirements ?? [])) {
            $requirements[] = 'Batch processing support';
            $score += 25;
        }

        return [
            'score'        => $score,
            'requirements' => $requirements,
            'passed'       => $score >= 50,
        ];
    }

    /**
     * Assess security measures.
     */
    private function assessSecurityMeasures(FinancialInstitutionApplication $application): array
    {
        $score = 0;
        $measures = [];

        if ($application->is_pci_compliant) {
            $score += 30;
            $measures[] = 'PCI-DSS compliant';
        }

        $certifications = $application->security_certifications ?? [];
        if (in_array('ISO27001', $certifications)) {
            $score += 25;
            $measures[] = 'ISO 27001 certified';
        }

        if (in_array('SOC2', $certifications)) {
            $score += 25;
            $measures[] = 'SOC 2 certified';
        }

        if ($application->has_data_protection_policy) {
            $score += 20;
            $measures[] = 'Data protection policy in place';
        }

        return [
            'score'    => $score,
            'measures' => $measures,
            'passed'   => $score >= 50,
        ];
    }

    /**
     * Assess scalability.
     */
    private function assessScalability(FinancialInstitutionApplication $application): array
    {
        $monthlyTransactions = $application->expected_monthly_transactions ?? 0;
        $monthlyVolume = $application->expected_monthly_volume ?? 0;

        $scalabilityScore = 0;
        $factors = [];

        if ($monthlyTransactions > 10000) {
            $scalabilityScore += 30;
            $factors[] = 'High transaction volume capability';
        }

        if ($monthlyVolume > 1000000) {
            $scalabilityScore += 30;
            $factors[] = 'High monetary volume capability';
        }

        if ($application->years_in_operation >= 5) {
            $scalabilityScore += 20;
            $factors[] = 'Established institution';
        }

        if (count($application->target_markets ?? []) > 5) {
            $scalabilityScore += 20;
            $factors[] = 'Multi-market presence';
        }

        return [
            'score'   => $scalabilityScore,
            'factors' => $factors,
            'passed'  => true, // Scalability is informational, not a hard requirement
        ];
    }
}
