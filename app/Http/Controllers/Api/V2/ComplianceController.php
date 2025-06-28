<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\KycVerification;
use App\Models\AmlScreening;
use App\Models\CustomerRiskProfile;
use App\Domain\Compliance\Services\EnhancedKycService;
use App\Domain\Compliance\Services\AmlScreeningService;
use App\Domain\Compliance\Services\CustomerRiskService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
     * Get user's KYC status
     */
    public function getKycStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $verifications = KycVerification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $profile = CustomerRiskProfile::where('user_id', $user->id)->first();
        
        return response()->json([
            'data' => [
                'kyc_level' => $user->kyc_level,
                'kyc_status' => $user->kyc_status,
                'risk_rating' => $profile?->risk_rating ?? 'unknown',
                'requires_verification' => $this->determineRequiredVerifications($user),
                'verifications' => $verifications->map(fn($v) => [
                    'id' => $v->id,
                    'type' => $v->type,
                    'status' => $v->status,
                    'completed_at' => $v->completed_at?->toIso8601String(),
                    'expires_at' => $v->expires_at?->toIso8601String(),
                ]),
                'limits' => [
                    'daily' => $profile?->daily_transaction_limit ?? 0,
                    'monthly' => $profile?->monthly_transaction_limit ?? 0,
                    'single' => $profile?->single_transaction_limit ?? 0,
                ],
            ],
        ]);
    }
    
    /**
     * Start KYC verification
     */
    public function startVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:identity,address,income,enhanced_due_diligence',
            'provider' => 'nullable|string|in:jumio,onfido,manual',
        ]);
        
        $user = $request->user();
        
        try {
            $verification = $this->kycService->startVerification(
                $user,
                $validated['type'],
                ['provider' => $validated['provider'] ?? 'manual']
            );
            
            return response()->json([
                'data' => [
                    'verification_id' => $verification->id,
                    'verification_number' => $verification->verification_number,
                    'type' => $verification->type,
                    'status' => $verification->status,
                    'provider' => $verification->provider,
                    'next_steps' => $this->getVerificationNextSteps($verification),
                ],
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Failed to start KYC verification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Failed to start verification',
            ], 422);
        }
    }
    
    /**
     * Upload verification document
     */
    public function uploadDocument(Request $request, string $verificationId): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => 'required|string',
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'document_side' => 'nullable|string|in:front,back',
        ]);
        
        $user = $request->user();
        $verification = KycVerification::where('id', $verificationId)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        if (!$verification->isPending() && !$verification->isInProgress()) {
            return response()->json([
                'error' => 'Verification is not in a valid state for document upload',
            ], 422);
        }
        
        try {
            $documentPath = $request->file('document')->store('kyc-temp');
            
            $result = match($verification->type) {
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
                default => throw new \Exception('Unsupported verification type'),
            };
            
            return response()->json([
                'data' => [
                    'success' => $result['success'],
                    'verification_id' => $verification->id,
                    'confidence_score' => $result['confidence_score'] ?? null,
                    'next_steps' => $this->getVerificationNextSteps($verification->fresh()),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'verification_id' => $verificationId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Document verification failed',
            ], 422);
        }
    }
    
    /**
     * Upload selfie for biometric verification
     */
    public function uploadSelfie(Request $request, string $verificationId): JsonResponse
    {
        $validated = $request->validate([
            'selfie' => 'required|file|mimes:jpg,jpeg,png|max:5120',
        ]);
        
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
            
            return response()->json([
                'data' => [
                    'success' => $result['success'],
                    'liveness_score' => $result['liveness_score'],
                    'face_match_score' => $result['face_match_score'],
                    'verification_status' => $verification->fresh()->status,
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Selfie verification failed', [
                'verification_id' => $verificationId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Biometric verification failed',
            ], 422);
        }
    }
    
    /**
     * Get AML screening status
     */
    public function getScreeningStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $screenings = AmlScreening::where('entity_id', $user->id)
            ->where('entity_type', User::class)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $profile = CustomerRiskProfile::where('user_id', $user->id)->first();
        
        return response()->json([
            'data' => [
                'is_pep' => $profile?->is_pep ?? false,
                'is_sanctioned' => $profile?->is_sanctioned ?? false,
                'has_adverse_media' => $profile?->has_adverse_media ?? false,
                'last_screening_date' => $screenings->first()?->created_at?->toIso8601String(),
                'screenings' => $screenings->map(fn($s) => [
                    'id' => $s->id,
                    'screening_number' => $s->screening_number,
                    'type' => $s->type,
                    'status' => $s->status,
                    'overall_risk' => $s->overall_risk,
                    'total_matches' => $s->total_matches,
                    'completed_at' => $s->completed_at?->toIso8601String(),
                ]),
            ],
        ]);
    }
    
    /**
     * Request manual screening
     */
    public function requestScreening(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:sanctions,pep,adverse_media,comprehensive',
            'reason' => 'nullable|string|max:500',
        ]);
        
        $user = $request->user();
        
        try {
            $screening = $this->amlService->performComprehensiveScreening($user, [
                'requested_by_user' => true,
                'reason' => $validated['reason'] ?? null,
            ]);
            
            return response()->json([
                'data' => [
                    'screening_id' => $screening->id,
                    'screening_number' => $screening->screening_number,
                    'status' => $screening->status,
                    'estimated_completion' => now()->addMinutes(5)->toIso8601String(),
                ],
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Screening request failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Failed to initiate screening',
            ], 422);
        }
    }
    
    /**
     * Get risk profile
     */
    public function getRiskProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = CustomerRiskProfile::where('user_id', $user->id)->first();
        
        if (!$profile) {
            $profile = $this->riskService->createOrUpdateProfile($user);
        }
        
        return response()->json([
            'data' => [
                'profile_number' => $profile->profile_number,
                'risk_rating' => $profile->risk_rating,
                'risk_score' => $profile->risk_score,
                'cdd_level' => $profile->cdd_level,
                'factors' => $this->summarizeRiskFactors($profile),
                'limits' => [
                    'daily' => $profile->daily_transaction_limit,
                    'monthly' => $profile->monthly_transaction_limit,
                    'single' => $profile->single_transaction_limit,
                ],
                'restrictions' => [
                    'countries' => $profile->restricted_countries ?? [],
                    'currencies' => $profile->restricted_currencies ?? [],
                ],
                'enhanced_monitoring' => $profile->enhanced_monitoring,
                'next_review_date' => $profile->next_review_at?->toIso8601String(),
            ],
        ]);
    }
    
    /**
     * Check transaction eligibility
     */
    public function checkTransactionEligibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'type' => 'required|string',
            'destination_country' => 'nullable|string|size:2',
        ]);
        
        $user = $request->user();
        $result = $this->riskService->canPerformTransaction(
            $user,
            $validated['amount'],
            $validated['currency']
        );
        
        return response()->json([
            'data' => [
                'allowed' => $result['allowed'],
                'reason' => $result['reason'],
                'limit' => $result['limit'] ?? null,
                'current_usage' => $result['current'] ?? null,
                'requires_additional_verification' => $this->checkAdditionalVerificationNeeded(
                    $user,
                    $validated
                ),
            ],
        ]);
    }
    
    /**
     * Determine required verifications
     */
    protected function determineRequiredVerifications(User $user): array
    {
        $required = [];
        
        if ($user->kyc_status === 'not_started' || !$user->kyc_level) {
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
     * Get verification next steps
     */
    protected function getVerificationNextSteps(KycVerification $verification): array
    {
        if ($verification->isCompleted()) {
            return ['verification_complete'];
        }
        
        $steps = [];
        
        if (!$verification->document_type) {
            $steps[] = 'upload_identity_document';
        }
        
        if ($verification->type === KycVerification::TYPE_IDENTITY && !$verification->verification_data) {
            $steps[] = 'upload_selfie';
        }
        
        if ($verification->type === KycVerification::TYPE_ADDRESS && !$verification->address_line1) {
            $steps[] = 'upload_address_proof';
        }
        
        return $steps;
    }
    
    /**
     * Summarize risk factors
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
     * Check if additional verification needed
     */
    protected function checkAdditionalVerificationNeeded(User $user, array $transaction): bool
    {
        // Large transactions may require additional verification
        if ($transaction['amount'] > 50000) {
            return true;
        }
        
        // High-risk countries
        if (isset($transaction['destination_country']) && 
            in_array($transaction['destination_country'], CustomerRiskProfile::HIGH_RISK_COUNTRIES)) {
            return true;
        }
        
        return false;
    }
}