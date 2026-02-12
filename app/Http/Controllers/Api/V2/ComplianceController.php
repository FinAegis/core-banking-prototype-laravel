<?php

namespace App\Http\Controllers\Api\V2;

use App\Domain\Compliance\Models\AmlScreening;
use App\Domain\Compliance\Models\CustomerRiskProfile;
use App\Domain\Compliance\Models\KycVerification;
use App\Domain\Compliance\Services\AmlScreeningService;
use App\Domain\Compliance\Services\CustomerRiskService;
use App\Domain\Compliance\Services\EnhancedKycService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ComplianceController extends Controller
{
    private EnhancedKycService $kycService;

    private AmlScreeningService $amlService;

    private CustomerRiskService $riskService;

    public function __construct(
        EnhancedKycService $kycService,
        AmlScreeningService $amlService,
        CustomerRiskService $riskService
    ) {
        $this->kycService = $kycService;
        $this->amlService = $amlService;
        $this->riskService = $riskService;
    }

    /**
     * Get user's KYC status.
     *
     * @OA\Get(
     *     path="/api/v2/compliance/kyc/status",
     *     operationId="complianceV2GetKycStatus",
     *     summary="Get KYC status",
     *     description="Returns the current KYC verification status, risk rating, verification history, and transaction limits for the authenticated user.",
     *     tags={"Compliance V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="kyc_level", type="string", example="basic"),
     *                 @OA\Property(property="kyc_status", type="string", example="verified"),
     *                 @OA\Property(property="risk_rating", type="string", example="low"),
     *                 @OA\Property(property="requires_verification", type="array", @OA\Items(type="string", example="address")),
     *                 @OA\Property(property="verifications", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id", type="string"),
     *                         @OA\Property(property="type", type="string", example="identity"),
     *                         @OA\Property(property="status", type="string", example="completed"),
     *                         @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
     *                         @OA\Property(property="expires_at", type="string", format="date-time", nullable=true)
     *                     )
     *                 ),
     *                 @OA\Property(property="limits", type="object",
     *                     @OA\Property(property="daily", type="number", example=10000),
     *                     @OA\Property(property="monthly", type="number", example=50000),
     *                     @OA\Property(property="single", type="number", example=5000)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getKycStatus(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $verifications = KycVerification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $profile = CustomerRiskProfile::where('user_id', $user->id)->first();

        return response()->json(
            [
                'data' => [
                    'kyc_level'             => $user->kyc_level,
                    'kyc_status'            => $user->kyc_status,
                    'risk_rating'           => $profile?->risk_rating ?? 'unknown',
                    'requires_verification' => $this->determineRequiredVerifications($user),
                    'verifications'         => $verifications->map(
                        fn ($v) => [
                            'id'           => $v->id,
                            'type'         => $v->type,
                            'status'       => $v->status,
                            'completed_at' => $v->completed_at?->toIso8601String(),
                            'expires_at'   => $v->expires_at?->toIso8601String(),
                        ]
                    ),
                    'limits' => [
                        'daily'   => $profile?->daily_transaction_limit ?? 0,
                        'monthly' => $profile?->monthly_transaction_limit ?? 0,
                        'single'  => $profile?->single_transaction_limit ?? 0,
                    ],
                ],
            ]
        );
    }

    /**
     * Start KYC verification.
     *
     * @OA\Post(
     *     path="/api/v2/compliance/kyc/start",
     *     operationId="complianceV2StartVerification",
     *     summary="Start KYC verification",
     *     description="Initiates a new KYC verification process for the authenticated user with the specified type and optional provider.",
     *     tags={"Compliance V2"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type"},
     *             @OA\Property(property="type", type="string", enum={"identity", "address", "income", "enhanced_due_diligence"}, example="identity"),
     *             @OA\Property(property="provider", type="string", enum={"jumio", "onfido", "manual"}, nullable=true, example="onfido")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Verification started successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="verification_id", type="string"),
     *                 @OA\Property(property="verification_number", type="string"),
     *                 @OA\Property(property="type", type="string", example="identity"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="provider", type="string", example="onfido"),
     *                 @OA\Property(property="next_steps", type="array", @OA\Items(type="string", example="upload_identity_document"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error or verification failed to start")
     * )
     */
    public function startVerification(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'type'     => 'required|string|in:identity,address,income,enhanced_due_diligence',
                'provider' => 'nullable|string|in:jumio,onfido,manual',
            ]
        );

        /** @var User $user */
        $user = $request->user();

        try {
            $verification = $this->kycService->startVerification(
                $user,
                $validated['type'],
                ['provider' => $validated['provider'] ?? 'manual']
            );

            return response()->json(
                [
                    'data' => [
                        'verification_id'     => $verification->id,
                        'verification_number' => $verification->verification_number,
                        'type'                => $verification->type,
                        'status'              => $verification->status,
                        'provider'            => $verification->provider,
                        'next_steps'          => $this->getVerificationNextSteps($verification),
                    ],
                ],
                201
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to start KYC verification',
                [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to start verification',
                ],
                422
            );
        }
    }

    /**
     * Upload verification document.
     *
     * @OA\Post(
     *     path="/api/v2/compliance/kyc/{verificationId}/document",
     *     operationId="complianceV2UploadDocument",
     *     summary="Upload verification document",
     *     description="Uploads a document (identity or address proof) for an active KYC verification. Supports JPG, PNG, and PDF up to 10MB.",
     *     tags={"Compliance V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="verificationId",
     *         in="path",
     *         required=true,
     *         description="The verification ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"document_type", "document"},
     *                 @OA\Property(property="document_type", type="string", description="Type of document being uploaded", example="passport"),
     *                 @OA\Property(property="document", type="string", format="binary", description="Document file (jpg, jpeg, png, pdf, max 10MB)"),
     *                 @OA\Property(property="document_side", type="string", enum={"front", "back"}, nullable=true, description="Which side of the document")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="verification_id", type="string"),
     *                 @OA\Property(property="confidence_score", type="number", format="float", nullable=true, example=95.5),
     *                 @OA\Property(property="next_steps", type="array", @OA\Items(type="string", example="upload_selfie"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Verification not found"),
     *     @OA\Response(response=422, description="Verification not in valid state or document verification failed")
     * )
     */
    public function uploadDocument(Request $request, string $verificationId): JsonResponse
    {
        $validated = $request->validate(
            [
                'document_type' => 'required|string',
                'document'      => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
                'document_side' => 'nullable|string|in:front,back',
            ]
        );

        /** @var User $user */
        $user = $request->user();
        $verification = KycVerification::where('id', $verificationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (! $verification->isPending() && ! $verification->isInProgress()) {
            return response()->json(
                [
                    'error' => 'Verification is not in a valid state for document upload',
                ],
                422
            );
        }

        try {
            $documentPath = $request->file('document')->store('kyc-temp');

            $result = match ($verification->type) {
                KycVerification::TYPE_IDENTITY => $this->kycService->verifyIdentityDocument(
                    $verification,
                    storage_path('app/' . $documentPath),
                    $validated['document_type']
                ),
                KycVerification::TYPE_ADDRESS => $this->kycService->verifyAddress(
                    $verification,
                    storage_path('app/' . $documentPath),
                    $validated['document_type']
                ),
                default => throw new Exception('Unsupported verification type'),
            };

            $responseData = [
                'success'          => $result['success'],
                'verification_id'  => $verification->id,
                'confidence_score' => $result['confidence_score'] ?? null,
            ];

            if ($verification !== null) {
                $responseData['next_steps'] = $this->getVerificationNextSteps($verification->fresh());
            }

            return response()->json(['data' => $responseData]);
        } catch (Exception $e) {
            Log::error(
                'Document upload failed',
                [
                    'verification_id' => $verificationId,
                    'error'           => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Document verification failed',
                ],
                422
            );
        }
    }

    /**
     * Upload selfie for biometric verification.
     *
     * @OA\Post(
     *     path="/api/v2/compliance/kyc/{verificationId}/selfie",
     *     operationId="complianceV2UploadSelfie",
     *     summary="Upload selfie for biometric verification",
     *     description="Uploads a selfie image for biometric liveness and face-match verification. If all checks pass with sufficient confidence, the verification is automatically completed.",
     *     tags={"Compliance V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="verificationId",
     *         in="path",
     *         required=true,
     *         description="The verification ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"selfie"},
     *                 @OA\Property(property="selfie", type="string", format="binary", description="Selfie image file (jpg, jpeg, png, max 5MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Selfie processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="liveness_score", type="number", format="float", example=98.2),
     *                 @OA\Property(property="face_match_score", type="number", format="float", example=95.7),
     *                 @OA\Property(property="verification_status", type="string", example="completed")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Verification not found"),
     *     @OA\Response(response=422, description="Biometric verification failed")
     * )
     */
    public function uploadSelfie(Request $request, string $verificationId): JsonResponse
    {
        $validated = $request->validate(
            [
                'selfie' => 'required|file|mimes:jpg,jpeg,png|max:5120',
            ]
        );

        /** @var User $user */
        $user = $request->user();
        $verification = KycVerification::where('id', $verificationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            $selfiePath = $request->file('selfie')->store('kyc-temp');

            // Get document image path if available
            $documentImagePath = null; // Would be extracted from verification data

            $result = $this->kycService->verifyBiometrics(
                $verification,
                storage_path('app/' . $selfiePath),
                $documentImagePath
            );

            // Complete verification if all checks pass
            if ($result['success'] && $verification->confidence_score >= 80) {
                $this->kycService->completeVerification($verification);
            }

            return response()->json(
                [
                    'data' => [
                        'success'             => $result['success'],
                        'liveness_score'      => $result['liveness_score'],
                        'face_match_score'    => $result['face_match_score'],
                        'verification_status' => $verification->fresh()->status,
                    ],
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Selfie verification failed',
                [
                    'verification_id' => $verificationId,
                    'error'           => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Biometric verification failed',
                ],
                422
            );
        }
    }

    /**
     * Get AML screening status.
     *
     * @OA\Get(
     *     path="/api/v2/compliance/aml/status",
     *     operationId="complianceV2GetScreeningStatus",
     *     summary="Get AML screening status",
     *     description="Returns the current AML screening status, including PEP/sanctions/adverse media flags and screening history for the authenticated user.",
     *     tags={"Compliance V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="is_pep", type="boolean", example=false),
     *                 @OA\Property(property="is_sanctioned", type="boolean", example=false),
     *                 @OA\Property(property="has_adverse_media", type="boolean", example=false),
     *                 @OA\Property(property="last_screening_date", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="screenings", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id", type="string"),
     *                         @OA\Property(property="screening_number", type="string"),
     *                         @OA\Property(property="type", type="string", example="comprehensive"),
     *                         @OA\Property(property="status", type="string", example="completed"),
     *                         @OA\Property(property="overall_risk", type="string", example="low"),
     *                         @OA\Property(property="total_matches", type="integer", example=0),
     *                         @OA\Property(property="completed_at", type="string", format="date-time", nullable=true)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getScreeningStatus(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $screenings = AmlScreening::where('entity_id', $user->uuid)
            ->where('entity_type', 'user')
            ->orderBy('created_at', 'desc')
            ->get();

        $profile = CustomerRiskProfile::where('user_id', $user->id)->first();

        return response()->json(
            [
                'data' => [
                    'is_pep'              => $profile?->is_pep ?? false,
                    'is_sanctioned'       => $profile?->is_sanctioned ?? false,
                    'has_adverse_media'   => $profile?->has_adverse_media ?? false,
                    'last_screening_date' => $screenings->first()?->created_at?->toIso8601String(),
                    'screenings'          => $screenings->map(
                        fn ($s) => [
                            'id'               => $s->id,
                            'screening_number' => $s->screening_number,
                            'type'             => $s->type,
                            'status'           => $s->status,
                            'overall_risk'     => $s->overall_risk,
                            'total_matches'    => $s->total_matches,
                            'completed_at'     => $s->completed_at?->toIso8601String(),
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * Request AML screening.
     *
     * @OA\Post(
     *     path="/api/v2/compliance/aml/request-screening",
     *     operationId="complianceV2RequestScreening",
     *     summary="Request AML screening",
     *     description="Initiates a comprehensive AML screening for the authenticated user, checking sanctions lists, PEP databases, and adverse media.",
     *     tags={"Compliance V2"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type"},
     *             @OA\Property(property="type", type="string", enum={"sanctions", "pep", "adverse_media", "comprehensive"}, example="comprehensive"),
     *             @OA\Property(property="reason", type="string", nullable=true, maxLength=500, example="Routine periodic review")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Screening initiated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="screening_id", type="string"),
     *                 @OA\Property(property="screening_number", type="string"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="estimated_completion", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error or screening failed to initiate")
     * )
     */
    public function requestScreening(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'type'   => 'required|string|in:sanctions,pep,adverse_media,comprehensive',
                'reason' => 'nullable|string|max:500',
            ]
        );

        /** @var User $user */
        $user = $request->user();

        try {
            $screening = $this->amlService->performComprehensiveScreening(
                $user,
                [
                    'requested_by_user' => true,
                    'reason'            => $validated['reason'] ?? null,
                ]
            );

            return response()->json(
                [
                    'data' => [
                        'screening_id'         => $screening->id,
                        'screening_number'     => $screening->screening_number,
                        'status'               => $screening->status,
                        'estimated_completion' => now()->addMinutes(5)->toIso8601String(),
                    ],
                ],
                201
            );
        } catch (Exception $e) {
            Log::error(
                'Screening request failed',
                [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'error' => 'Failed to initiate screening',
                ],
                422
            );
        }
    }

    /**
     * Get user's risk profile.
     *
     * @OA\Get(
     *     path="/api/v2/compliance/risk-profile",
     *     operationId="complianceV2GetRiskProfile",
     *     summary="Get risk profile",
     *     description="Returns the authenticated user's compliance risk profile including risk rating, score, CDD level, transaction limits, country/currency restrictions, and monitoring status.",
     *     tags={"Compliance V2"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="profile_number", type="string"),
     *                 @OA\Property(property="risk_rating", type="string", example="low"),
     *                 @OA\Property(property="risk_score", type="number", example=25),
     *                 @OA\Property(property="cdd_level", type="string", example="standard"),
     *                 @OA\Property(property="factors", type="array", @OA\Items(type="string", example="high_risk_geography")),
     *                 @OA\Property(property="limits", type="object",
     *                     @OA\Property(property="daily", type="number", example=10000),
     *                     @OA\Property(property="monthly", type="number", example=50000),
     *                     @OA\Property(property="single", type="number", example=5000)
     *                 ),
     *                 @OA\Property(property="restrictions", type="object",
     *                     @OA\Property(property="countries", type="array", @OA\Items(type="string", example="KP")),
     *                     @OA\Property(property="currencies", type="array", @OA\Items(type="string", example="XMR"))
     *                 ),
     *                 @OA\Property(property="enhanced_monitoring", type="boolean", example=false),
     *                 @OA\Property(property="next_review_date", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getRiskProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = CustomerRiskProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            $profile = $this->riskService->createOrUpdateProfile($user);
        }

        return response()->json(
            [
                'data' => [
                    'profile_number' => $profile->profile_number,
                    'risk_rating'    => $profile->risk_rating,
                    'risk_score'     => $profile->risk_score,
                    'cdd_level'      => $profile->cdd_level,
                    'factors'        => $this->summarizeRiskFactors($profile),
                    'limits'         => [
                        'daily'   => $profile->daily_transaction_limit,
                        'monthly' => $profile->monthly_transaction_limit,
                        'single'  => $profile->single_transaction_limit,
                    ],
                    'restrictions' => [
                        'countries'  => $profile->restricted_countries ?? [],
                        'currencies' => $profile->restricted_currencies ?? [],
                    ],
                    'enhanced_monitoring' => $profile->enhanced_monitoring,
                    'next_review_date'    => $profile->next_review_at?->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Check transaction eligibility.
     *
     * @OA\Post(
     *     path="/api/v2/compliance/check-transaction",
     *     operationId="complianceV2CheckTransactionEligibility",
     *     summary="Check transaction eligibility",
     *     description="Checks whether the authenticated user is allowed to perform a transaction of the specified amount, currency, and type, based on their compliance profile and limits.",
     *     tags={"Compliance V2"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "currency", "type"},
     *             @OA\Property(property="amount", type="number", example=1500.00),
     *             @OA\Property(property="currency", type="string", minLength=3, maxLength=3, example="USD"),
     *             @OA\Property(property="type", type="string", example="transfer"),
     *             @OA\Property(property="destination_country", type="string", minLength=2, maxLength=2, nullable=true, example="DE")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eligibility check result",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="allowed", type="boolean", example=true),
     *                 @OA\Property(property="reason", type="string", nullable=true, example="Within daily limit"),
     *                 @OA\Property(property="limit", type="number", nullable=true, example=10000),
     *                 @OA\Property(property="current_usage", type="number", nullable=true, example=3500),
     *                 @OA\Property(property="requires_additional_verification", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function checkTransactionEligibility(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'amount'              => 'required|numeric|min:0',
                'currency'            => 'required|string|size:3',
                'type'                => 'required|string',
                'destination_country' => 'nullable|string|size:2',
            ]
        );

        /** @var User $user */
        $user = $request->user();
        $result = $this->riskService->canPerformTransaction(
            $user,
            $validated['amount'],
            $validated['currency']
        );

        return response()->json(
            [
                'data' => [
                    'allowed'                          => $result['allowed'],
                    'reason'                           => $result['reason'],
                    'limit'                            => $result['limit'] ?? null,
                    'current_usage'                    => $result['current'] ?? null,
                    'requires_additional_verification' => $this->checkAdditionalVerificationNeeded(
                        $user,
                        $validated
                    ),
                ],
            ]
        );
    }

    /**
     * Determine required verifications.
     */
    protected function determineRequiredVerifications(User $user): array
    {
        $required = [];

        if ($user->kyc_status === 'not_started' || ! $user->kyc_level) {
            $required[] = 'identity';
        }

        if ($user->kyc_level === 'basic') {
            $required[] = 'address';
        }

        $profile = CustomerRiskProfile::where('user_id', $user->id)->first();
        if ($profile && $profile->requiresEnhancedDueDiligence()) {
            $required[] = 'enhanced_due_diligence';
        }

        return $required;
    }

    /**
     * Get verification next steps.
     */
    protected function getVerificationNextSteps(KycVerification $verification): array
    {
        if ($verification->isCompleted()) {
            return ['verification_complete'];
        }

        $steps = [];

        if (! $verification->document_type) {
            $steps[] = 'upload_identity_document';
        }

        if ($verification->type === KycVerification::TYPE_IDENTITY && ! $verification->verification_data) {
            $steps[] = 'upload_selfie';
        }

        if ($verification->type === KycVerification::TYPE_ADDRESS && ! $verification->address_line1) {
            $steps[] = 'upload_address_proof';
        }

        return $steps;
    }

    /**
     * Summarize risk factors.
     */
    protected function summarizeRiskFactors(CustomerRiskProfile $profile): array
    {
        $factors = [];

        if ($profile->is_pep) {
            $factors[] = 'politically_exposed_person';
        }

        if ($profile->is_sanctioned) {
            $factors[] = 'sanctions_match';
        }

        if ($profile->has_adverse_media) {
            $factors[] = 'adverse_media';
        }

        $geoRisk = $profile->geographic_risk ?? [];
        if (($geoRisk['score'] ?? 0) >= 60) {
            $factors[] = 'high_risk_geography';
        }

        if ($profile->suspicious_activities_count > 0) {
            $factors[] = 'suspicious_activity_history';
        }

        return $factors;
    }

    /**
     * Check if additional verification needed.
     */
    protected function checkAdditionalVerificationNeeded(User $user, array $transaction): bool
    {
        // Large transactions may require additional verification
        if ($transaction['amount'] > 50000) {
            return true;
        }

        // High-risk countries
        if (
            isset($transaction['destination_country'])
            && in_array($transaction['destination_country'], CustomerRiskProfile::HIGH_RISK_COUNTRIES)
        ) {
            return true;
        }

        return false;
    }
}
