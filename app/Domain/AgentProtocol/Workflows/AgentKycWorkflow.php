<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows;

use App\Domain\AgentProtocol\Aggregates\AgentComplianceAggregate;
use App\Domain\AgentProtocol\DataObjects\KycVerificationRequest;
use App\Domain\AgentProtocol\DataObjects\KycVerificationResult;
use App\Domain\AgentProtocol\Enums\KycVerificationLevel;
use App\Domain\AgentProtocol\Enums\KycVerificationStatus;
use App\Domain\AgentProtocol\Workflows\Activities\CalculateRiskScoreActivity;
use App\Domain\AgentProtocol\Workflows\Activities\NotifyComplianceOfficerActivity;
use App\Domain\AgentProtocol\Workflows\Activities\PerformAmlScreeningActivity;
use App\Domain\AgentProtocol\Workflows\Activities\PerformBiometricVerificationActivity;
use App\Domain\AgentProtocol\Workflows\Activities\SetInitialTransactionLimitsActivity;
use App\Domain\AgentProtocol\Workflows\Activities\UpdateAgentStatusActivity;
use App\Domain\AgentProtocol\Workflows\Activities\ValidateKycDocumentsActivity;
use App\Domain\AgentProtocol\Workflows\Activities\VerifyBusinessRegistrationActivity;
use App\Domain\AgentProtocol\Workflows\Activities\VerifyIdentityDocumentActivity;
use App\Domain\AgentProtocol\Workflows\Activities\VerifyProofOfAddressActivity;
use Carbon\CarbonInterval;
use Exception;
use Generator;
use Illuminate\Support\Str;
use Workflow\Activity;
use Workflow\Workflow;

class AgentKycWorkflow extends Workflow
{
    private AgentComplianceAggregate $aggregate;

    private array $compensations = [];

    private array $verificationResults = [];

    private int $riskScore = 0;

    private array $complianceFlags = [];

    /**
     * Execute KYC verification workflow.
     */
    public function execute(KycVerificationRequest $request): Generator
    {
        $this->aggregate = AgentComplianceAggregate::retrieve(
            Str::uuid()->toString()
        );

        try {
            // Step 1: Initiate KYC process
            $this->aggregate = AgentComplianceAggregate::initiateKyc(
                agentId: $request->agentId,
                agentDid: $request->agentDid,
                level: $request->verificationLevel,
                requiredDocuments: $request->verificationLevel->getRequiredDocuments()
            );
            $this->aggregate->persist();

            // Step 2: Validate documents
            $documentsValid = yield Activity::make(
                ValidateKycDocumentsActivity::class,
                [
                    'documents'         => $request->documents,
                    'requiredDocuments' => $request->verificationLevel->getRequiredDocuments(),
                ]
            )->withTimeout(CarbonInterval::seconds(30));

            if (! $documentsValid['valid']) {
                $this->aggregate->rejectKyc(
                    reason: 'Invalid or missing documents',
                    failedChecks: $documentsValid['failedChecks']
                );
                $this->aggregate->persist();

                return new KycVerificationResult(
                    success: false,
                    status: KycVerificationStatus::REJECTED,
                    reason: $documentsValid['reason'],
                    agentId: $request->agentId
                );
            }

            // Record documents submission
            $this->aggregate->submitDocuments($request->documents);
            $this->aggregate->persist();

            // Step 3: Verify identity document
            $identityResult = yield Activity::make(
                VerifyIdentityDocumentActivity::class,
                [
                    'documentPath' => $request->documents['government_id'] ?? null,
                    'agentId'      => $request->agentId,
                ]
            )->withTimeout(CarbonInterval::minutes(2));

            $this->verificationResults['identity'] = $identityResult;
            $this->compensations[] = [
                'activity' => UpdateAgentStatusActivity::class,
                'args'     => [
                    'agentId' => $request->agentId,
                    'status'  => 'kyc_pending',
                ],
            ];

            // Step 4: Perform AML screening
            $amlResult = yield Activity::make(
                PerformAmlScreeningActivity::class,
                [
                    'agentId'     => $request->agentId,
                    'agentName'   => $request->agentName,
                    'countryCode' => $request->countryCode,
                ]
            )->withTimeout(CarbonInterval::minutes(3));

            $this->verificationResults['aml'] = $amlResult;
            if ($amlResult['hasAlerts']) {
                $this->complianceFlags[] = 'aml_alert';
            }

            // Step 5: Additional verifications based on level
            yield from $this->performLevelSpecificVerifications($request);

            // Step 6: Calculate risk score
            $riskScoreData = yield Activity::make(
                CalculateRiskScoreActivity::class,
                [
                    'agentId'             => $request->agentId,
                    'verificationResults' => $this->verificationResults,
                    'amlAlerts'           => $amlResult['alerts'] ?? [],
                    'countryCode'         => $request->countryCode,
                ]
            )->withTimeout(CarbonInterval::seconds(30));

            $this->riskScore = $riskScoreData['score'];

            // Step 7: Determine verification outcome
            $verificationPassed = $this->evaluateVerificationResults();

            if (! $verificationPassed) {
                // Manual review required for high-risk or failed checks
                if ($this->riskScore > 70) {
                    yield Activity::make(
                        NotifyComplianceOfficerActivity::class,
                        [
                            'agentId'             => $request->agentId,
                            'reason'              => 'High risk score requires manual review',
                            'riskScore'           => $this->riskScore,
                            'verificationResults' => $this->verificationResults,
                        ]
                    )->withTimeout(CarbonInterval::seconds(10));

                    $this->aggregate = AgentComplianceAggregate::retrieve($this->aggregate->uuid());
                    $this->aggregate->recordThat(new \App\Domain\AgentProtocol\Events\AgentKycRequiresReview(
                        agentId: $request->agentId,
                        riskScore: $this->riskScore,
                        reason: 'High risk score',
                        reviewRequiredAt: now()
                    ));
                    $this->aggregate->persist();

                    return new KycVerificationResult(
                        success: false,
                        status: KycVerificationStatus::REQUIRES_REVIEW,
                        reason: 'Manual review required due to risk assessment',
                        agentId: $request->agentId,
                        riskScore: $this->riskScore
                    );
                }

                // Reject if verification failed
                $this->aggregate = AgentComplianceAggregate::retrieve($this->aggregate->uuid());
                $this->aggregate->rejectKyc(
                    reason: 'Verification checks failed',
                    failedChecks: $this->getFailedChecks()
                );
                $this->aggregate->persist();

                return new KycVerificationResult(
                    success: false,
                    status: KycVerificationStatus::REJECTED,
                    reason: 'Verification checks failed',
                    agentId: $request->agentId,
                    failedChecks: $this->getFailedChecks()
                );
            }

            // Step 8: Verify KYC and set expiration
            $expiresAt = now()->addDays($request->verificationLevel->getVerificationPeriodDays());

            $this->aggregate = AgentComplianceAggregate::retrieve($this->aggregate->uuid());
            $this->aggregate->verifyKyc(
                verificationResults: $this->verificationResults,
                riskScore: $this->riskScore,
                expiresAt: $expiresAt,
                complianceFlags: $this->complianceFlags
            );
            $this->aggregate->persist();

            // Step 9: Set initial transaction limits
            yield Activity::make(
                SetInitialTransactionLimitsActivity::class,
                [
                    'agentId'           => $request->agentId,
                    'verificationLevel' => $request->verificationLevel->value,
                    'riskScore'         => $this->riskScore,
                ]
            )->withTimeout(CarbonInterval::seconds(10));

            // Step 10: Update agent status
            yield Activity::make(
                UpdateAgentStatusActivity::class,
                [
                    'agentId' => $request->agentId,
                    'status'  => 'kyc_verified',
                ]
            )->withTimeout(CarbonInterval::seconds(10));

            return new KycVerificationResult(
                success: true,
                status: KycVerificationStatus::VERIFIED,
                agentId: $request->agentId,
                verificationLevel: $request->verificationLevel,
                riskScore: $this->riskScore,
                expiresAt: $expiresAt,
                transactionLimits: [
                    'daily'   => $this->aggregate->getDailyTransactionLimit(),
                    'weekly'  => $this->aggregate->getWeeklyTransactionLimit(),
                    'monthly' => $this->aggregate->getMonthlyTransactionLimit(),
                ]
            );
        } catch (Exception $e) {
            // Compensate for failed activities
            yield from $this->compensate();

            // Record failure
            $this->aggregate = AgentComplianceAggregate::retrieve($this->aggregate->uuid());
            $this->aggregate->rejectKyc(
                reason: 'Workflow execution failed: ' . $e->getMessage(),
                failedChecks: ['workflow_error']
            );
            $this->aggregate->persist();

            throw $e;
        }
    }

    /**
     * Perform level-specific verifications.
     */
    protected function performLevelSpecificVerifications(KycVerificationRequest $request): Generator
    {
        switch ($request->verificationLevel) {
            case KycVerificationLevel::ENHANCED:
            case KycVerificationLevel::FULL:
                // Verify proof of address
                if (isset($request->documents['proof_of_address'])) {
                    $addressResult = yield Activity::make(
                        VerifyProofOfAddressActivity::class,
                        [
                            'documentPath' => $request->documents['proof_of_address'],
                            'agentId'      => $request->agentId,
                        ]
                    )->withTimeout(CarbonInterval::minutes(1));

                    $this->verificationResults['address'] = $addressResult;
                }

                // Biometric verification for enhanced level
                if ($request->enableBiometric && isset($request->documents['selfie'])) {
                    $biometricResult = yield Activity::make(
                        PerformBiometricVerificationActivity::class,
                        [
                            'selfiePath'     => $request->documents['selfie'],
                            'idDocumentPath' => $request->documents['government_id'],
                            'agentId'        => $request->agentId,
                        ]
                    )->withTimeout(CarbonInterval::minutes(2));

                    $this->verificationResults['biometric'] = $biometricResult;
                }

                // Additional checks for FULL verification
                if ($request->verificationLevel === KycVerificationLevel::FULL) {
                    yield from $this->performFullVerification($request);
                }
                break;
        }
    }

    /**
     * Perform full verification checks.
     */
    protected function performFullVerification(KycVerificationRequest $request): Generator
    {
        // Verify business registration if provided
        if (isset($request->documents['business_registration'])) {
            $businessResult = yield Activity::make(
                VerifyBusinessRegistrationActivity::class,
                [
                    'documentPath' => $request->documents['business_registration'],
                    'businessName' => $request->businessName,
                    'countryCode'  => $request->countryCode,
                ]
            )->withTimeout(CarbonInterval::minutes(3));

            $this->verificationResults['business'] = $businessResult;

            if ($businessResult['status'] === 'verified' && $businessResult['isHighRisk']) {
                $this->complianceFlags[] = 'high_risk_business';
            }
        }
    }

    /**
     * Evaluate verification results.
     */
    protected function evaluateVerificationResults(): bool
    {
        // Check if all required verifications passed
        foreach ($this->verificationResults as $key => $result) {
            if ($result['status'] !== 'passed' && $result['status'] !== 'verified') {
                return false;
            }
        }

        // Check risk score threshold
        $maxRiskScore = match ($this->aggregate->getVerificationLevel()) {
            KycVerificationLevel::BASIC    => 70,
            KycVerificationLevel::ENHANCED => 50,
            KycVerificationLevel::FULL     => 30,
        };

        return $this->riskScore <= $maxRiskScore;
    }

    /**
     * Get failed verification checks.
     */
    protected function getFailedChecks(): array
    {
        $failedChecks = [];

        foreach ($this->verificationResults as $key => $result) {
            if ($result['status'] !== 'passed' && $result['status'] !== 'verified') {
                $failedChecks[] = $key;
            }
        }

        if ($this->riskScore > 70) {
            $failedChecks[] = 'risk_score_exceeded';
        }

        return $failedChecks;
    }

    /**
     * Execute compensation activities.
     */
    public function compensate(): Generator
    {
        foreach (array_reverse($this->compensations) as $compensation) {
            try {
                yield Activity::make(
                    $compensation['activity'],
                    $compensation['args']
                )->withTimeout(CarbonInterval::seconds(30));
            } catch (Exception $e) {
                // Log compensation failure but continue
                logger()->error('Compensation failed', [
                    'activity' => $compensation['activity'],
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }
}
