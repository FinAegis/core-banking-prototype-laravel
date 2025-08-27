<?php

namespace App\Domain\Compliance\Services;

use App\Domain\Compliance\Events\KycVerificationCompleted;
use App\Domain\Compliance\Events\KycVerificationStarted;
use App\Domain\Compliance\Models\CustomerRiskProfile;
use App\Domain\Compliance\Models\KycVerification;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnhancedKycService
{
    private IdentityVerificationService $identityService;

    private DocumentAnalysisService $documentService;

    private BiometricVerificationService $biometricService;

    public function __construct(
        IdentityVerificationService $identityService,
        DocumentAnalysisService $documentService,
        BiometricVerificationService $biometricService
    ) {
        $this->identityService = $identityService;
        $this->documentService = $documentService;
        $this->biometricService = $biometricService;
    }

    /**
     * Start KYC verification process.
     */
    public function startVerification(User $user, string $type, array $data): KycVerification
    {
        return DB::transaction(
            function () use ($user, $type, $data) {
                $verification = KycVerification::create(
                    [
                        'user_id'       => $user->id,
                        'type'          => $type,
                        'status'        => KycVerification::STATUS_PENDING,
                        'provider'      => $data['provider'] ?? 'manual',
                        'document_type' => $data['document_type'] ?? null,
                        'started_at'    => now(),
                    ]
                );

                event(new KycVerificationStarted($verification));

                return $verification;
            }
        );
    }

    /**
     * Verify identity document.
     */
    public function verifyIdentityDocument(
        KycVerification $verification,
        string $documentPath,
        string $documentType
    ): array {
        try {
            $verification->update(
                [
                    'status'        => KycVerification::STATUS_IN_PROGRESS,
                    'document_type' => $documentType,
                ]
            );

            // Extract document data
            $extractedData = $this->documentService->extractDocumentData($documentPath, $documentType);

            // Verify document authenticity
            $authenticityCheck = $this->documentService->verifyAuthenticity($documentPath, $documentType);

            // Cross-reference with identity databases
            $identityCheck = $this->identityService->verifyIdentity($extractedData);

            // Calculate confidence score
            $confidenceScore = $this->calculateIdentityConfidence($authenticityCheck, $identityCheck);

            $verification->update(
                [
                    'extracted_data'    => $extractedData,
                    'verification_data' => [
                        'authenticity' => $authenticityCheck,
                        'identity'     => $identityCheck,
                    ],
                    'confidence_score' => $confidenceScore,
                    'document_number'  => $extractedData['document_number'] ?? null,
                    'document_country' => $extractedData['issuing_country'] ?? null,
                    'document_expiry'  => $extractedData['expiry_date'] ?? null,
                    'first_name'       => $extractedData['first_name'] ?? null,
                    'last_name'        => $extractedData['last_name'] ?? null,
                    'date_of_birth'    => $extractedData['date_of_birth'] ?? null,
                    'nationality'      => $extractedData['nationality'] ?? null,
                ]
            );

            return [
                'success'          => true,
                'confidence_score' => $confidenceScore,
                'extracted_data'   => $extractedData,
            ];
        } catch (Exception $e) {
            Log::error(
                'Identity document verification failed',
                [
                    'verification_id' => $verification->id,
                    'error'           => $e->getMessage(),
                ]
            );

            $verification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform biometric verification.
     */
    public function verifyBiometrics(
        KycVerification $verification,
        string $selfiePath,
        ?string $documentImagePath = null
    ): array {
        try {
            // Perform liveness detection
            $livenessCheck = $this->biometricService->checkLiveness($selfiePath);

            if (! $livenessCheck['is_live']) {
                throw new Exception('Liveness check failed');
            }

            // Face matching if document image provided
            $faceMatch = null;
            if ($documentImagePath) {
                $faceMatch = $this->biometricService->matchFaces($selfiePath, $documentImagePath);
            }

            $biometricData = [
                'liveness'   => $livenessCheck,
                'face_match' => $faceMatch,
            ];

            $verification->update(
                [
                    'verification_data' => array_merge(
                        $verification->verification_data ?? [],
                        ['biometric' => $biometricData]
                    ),
                ]
            );

            return [
                'success'          => true,
                'liveness_score'   => $livenessCheck['confidence'],
                'face_match_score' => $faceMatch['similarity'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error(
                'Biometric verification failed',
                [
                    'verification_id' => $verification->id,
                    'error'           => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Verify address proof.
     */
    public function verifyAddress(
        KycVerification $verification,
        string $documentPath,
        string $documentType
    ): array {
        try {
            // Extract address from document
            $extractedData = $this->documentService->extractAddressData($documentPath, $documentType);

            // Validate address format and completeness
            $validationResult = $this->validateAddress($extractedData);

            // Verify document recency (e.g., utility bill not older than 3 months)
            $recencyCheck = $this->documentService->checkDocumentRecency($extractedData);

            $verification->update(
                [
                    'address_line1'     => $extractedData['line1'] ?? null,
                    'address_line2'     => $extractedData['line2'] ?? null,
                    'city'              => $extractedData['city'] ?? null,
                    'state'             => $extractedData['state'] ?? null,
                    'postal_code'       => $extractedData['postal_code'] ?? null,
                    'country'           => $extractedData['country'] ?? null,
                    'verification_data' => array_merge(
                        $verification->verification_data ?? [],
                        [
                            'address_validation' => $validationResult,
                            'document_recency'   => $recencyCheck,
                        ]
                    ),
                ]
            );

            return [
                'success'   => true,
                'address'   => $extractedData,
                'is_valid'  => $validationResult['is_valid'],
                'is_recent' => $recencyCheck['is_recent'],
            ];
        } catch (Exception $e) {
            Log::error(
                'Address verification failed',
                [
                    'verification_id' => $verification->id,
                    'error'           => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Complete verification process.
     */
    public function completeVerification(KycVerification $verification): void
    {
        DB::transaction(
            function () use ($verification) {
                // Perform final checks
                $riskAssessment = $this->assessVerificationRisk($verification);

                $verification->update(
                    [
                        'status'       => KycVerification::STATUS_COMPLETED,
                        'completed_at' => now(),
                        'risk_level'   => $riskAssessment['level'],
                        'risk_factors' => $riskAssessment['factors'],
                        'expires_at'   => $this->calculateExpiryDate($riskAssessment['level']),
                    ]
                );

                // Update user KYC status
                $this->updateUserKycStatus($verification);

                // Update or create risk profile
                $this->updateRiskProfile($verification);

                event(new KycVerificationCompleted($verification));
            }
        );
    }

    /**
     * Calculate identity confidence score.
     */
    protected function calculateIdentityConfidence(array $authenticityCheck, array $identityCheck): float
    {
        $weights = [
            'document_authentic'  => 0.3,
            'data_consistency'    => 0.2,
            'identity_match'      => 0.3,
            'no_fraud_indicators' => 0.2,
        ];

        $score = 0;

        if ($authenticityCheck['is_authentic'] ?? false) {
            $score += $weights['document_authentic'] * 100;
        }

        if ($authenticityCheck['data_consistency'] ?? false) {
            $score += $weights['data_consistency'] * 100;
        }

        if ($identityCheck['match_found'] ?? false) {
            $score += $weights['identity_match'] * ($identityCheck['match_confidence'] ?? 0);
        }

        if (! ($authenticityCheck['fraud_indicators'] ?? false)) {
            $score += $weights['no_fraud_indicators'] * 100;
        }

        return round($score, 2);
    }

    /**
     * Validate address format and completeness.
     */
    protected function validateAddress(array $address): array
    {
        $required = ['line1', 'city', 'country'];
        $missing = [];

        foreach ($required as $field) {
            if (empty($address[$field])) {
                $missing[] = $field;
            }
        }

        return [
            'is_valid'       => empty($missing),
            'missing_fields' => $missing,
            'completeness'   => (count($required) - count($missing)) / count($required) * 100,
        ];
    }

    /**
     * Assess verification risk.
     */
    protected function assessVerificationRisk(KycVerification $verification): array
    {
        $riskFactors = [];
        $riskScore = 0;

        // Low confidence score
        if ($verification->confidence_score < 70) {
            $riskFactors[] = 'low_confidence_score';
            $riskScore += 20;
        }

        // Document issues
        if ($verification->document_expiry && $verification->document_expiry->isPast()) {
            $riskFactors[] = 'expired_document';
            $riskScore += 15;
        }

        // PEP/Sanctions/Adverse Media
        if ($verification->pep_check) {
            $riskFactors[] = 'pep';
            $riskScore += 30;
        }

        if ($verification->sanctions_check) {
            $riskFactors[] = 'sanctions_hit';
            $riskScore += 50;
        }

        if ($verification->adverse_media_check) {
            $riskFactors[] = 'adverse_media';
            $riskScore += 25;
        }

        // Determine risk level
        $level = match (true) {
            $riskScore >= 50 => KycVerification::RISK_LEVEL_HIGH,
            $riskScore >= 20 => KycVerification::RISK_LEVEL_MEDIUM,
            default          => KycVerification::RISK_LEVEL_LOW,
        };

        return [
            'level'   => $level,
            'score'   => $riskScore,
            'factors' => $riskFactors,
        ];
    }

    /**
     * Calculate verification expiry date.
     */
    protected function calculateExpiryDate(string $riskLevel): \Carbon\Carbon
    {
        return match ($riskLevel) {
            KycVerification::RISK_LEVEL_HIGH   => now()->addMonths(6),
            KycVerification::RISK_LEVEL_MEDIUM => now()->addYear(),
            default                            => now()->addYears(2),
        };
    }

    /**
     * Update user KYC status.
     */
    protected function updateUserKycStatus(KycVerification $verification): void
    {
        $user = $verification->user;

        // Determine KYC level based on verification types completed
        $completedTypes = KycVerification::where('user_id', $user->id)
            ->where('status', KycVerification::STATUS_COMPLETED)
            ->where('expires_at', '>', now())
            ->pluck('type')
            ->unique()
            ->toArray();

        $kycLevel = $this->determineKycLevel($completedTypes);

        $user->update(
            [
                'kyc_status'      => 'approved',
                'kyc_level'       => $kycLevel,
                'kyc_approved_at' => now(),
                'kyc_expires_at'  => $verification->expires_at,
                'risk_rating'     => $verification->risk_level,
            ]
        );
    }

    /**
     * Determine KYC level from completed verifications.
     */
    protected function determineKycLevel(array $completedTypes): string
    {
        if (in_array(KycVerification::TYPE_ENHANCED_DUE_DILIGENCE, $completedTypes)) {
            return 'full';
        }

        if (
            in_array(KycVerification::TYPE_INCOME, $completedTypes)
            && in_array(KycVerification::TYPE_ADDRESS, $completedTypes)
        ) {
            return 'enhanced';
        }

        if (in_array(KycVerification::TYPE_IDENTITY, $completedTypes)) {
            return 'basic';
        }

        return 'none';
    }

    /**
     * Update customer risk profile.
     */
    protected function updateRiskProfile(KycVerification $verification): void
    {
        $profile = CustomerRiskProfile::firstOrCreate(
            ['user_id' => $verification->user_id],
            [
                'risk_rating'               => CustomerRiskProfile::RISK_RATING_LOW,
                'risk_score'                => 0,
                'cdd_level'                 => CustomerRiskProfile::CDD_LEVEL_STANDARD,
                'daily_transaction_limit'   => 10000,
                'monthly_transaction_limit' => 100000,
                'single_transaction_limit'  => 5000,
            ]
        );

        // Update profile based on verification results
        $profile->update(
            [
                'last_assessment_at'       => now(),
                'next_review_at'           => $verification->expires_at,
                'is_pep'                   => $verification->pep_check,
                'pep_verified_at'          => $verification->pep_check ? now() : null,
                'is_sanctioned'            => $verification->sanctions_check,
                'sanctions_verified_at'    => now(),
                'has_adverse_media'        => $verification->adverse_media_check,
                'adverse_media_checked_at' => now(),
            ]
        );

        // Recalculate risk
        $profile->updateRiskAssessment();
    }
}
