<?php

namespace App\Http\Controllers;

use App\Domain\Cgo\Models\CgoInvestment;
use App\Domain\Cgo\Services\CgoKycService;
use App\Domain\Compliance\Models\KycDocument;
use App\Domain\Compliance\Services\KycService;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CgoKycController extends Controller
{
    protected CgoKycService $cgoKycService;

    protected KycService $kycService;

    public function __construct(CgoKycService $cgoKycService, KycService $kycService)
    {
        $this->cgoKycService = $cgoKycService;
        $this->kycService = $kycService;
    }

    /**
     * Check KYC requirements for a specific investment amount.
     */
    public function checkRequirements(Request $request)
    {
        $request->validate(
            [
                'amount' => 'required|numeric|min:1',
            ]
        );

        $user = Auth::user();
        /** @var User $user */

        // Create a temporary investment object for checking requirements
        $tempInvestment = new CgoInvestment(
            [
                'user_id' => $user->id,
                'amount'  => $request->amount,
            ]
        );

        $requirements = $this->cgoKycService->checkKycRequirements($tempInvestment);

        return response()->json(
            [
                'success' => true,
                'data'    => $requirements,
            ]
        );
    }

    /**
     * Get current KYC status for CGO investments.
     */
    public function status()
    {
        $user = Auth::user();
        /** @var User $user */
        $totalInvested = CgoInvestment::where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->sum('amount');

        $investmentLimits = $this->getInvestmentLimits($user);
        $documents = $this->documents()->getData()->data ?? [];

        // Get required documents based on next level
        $requiredDocuments = [];
        if (! $user->kyc_level || $user->kyc_level === 'none') {
            $requiredDocuments = $this->kycService->getRequirements('basic')['documents'];
        } elseif ($user->kyc_level === 'basic') {
            $requiredDocuments = $this->kycService->getRequirements('enhanced')['documents'];
        } elseif ($user->kyc_level === 'enhanced') {
            $requiredDocuments = $this->kycService->getRequirements('full')['documents'];
        }

        return view(
            'cgo.kyc-status',
            [
                'totalInvested'     => $totalInvested,
                'availableLimit'    => $investmentLimits['available_limit'],
                'documents'         => $documents,
                'requiredDocuments' => $requiredDocuments,
                'requiredActions'   => $this->getRequiredActions($user),
            ]
        );
    }

    /**
     * Submit KYC documents for CGO investment.
     */
    public function submitDocuments(Request $request)
    {
        $request->validate(
            [
                'documents'        => 'required|array|min:1',
                'documents.*.type' => 'required|string|in:passport,driving_license,national_id,utility_bill,bank_statement,selfie,proof_of_income,source_of_funds',
                'documents.*.file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB max
                'investment_id'    => 'nullable|exists:cgo_investments,uuid',
            ]
        );

        $user = Auth::user();
        /** @var User $user */
        try {
            DB::beginTransaction();

            // If specific investment is referenced, check it
            if ($request->investment_id) {
                $investment = CgoInvestment::where('uuid', $request->investment_id)
                    ->where('user_id', $user->id)
                    ->firstOrFail();

                // Check if this investment requires KYC
                $requirements = $this->cgoKycService->checkKycRequirements($investment);
                if ($requirements['is_sufficient']) {
                    return response()->json(
                        [
                            'success' => false,
                            'message' => 'KYC is already sufficient for this investment',
                        ],
                        400
                    );
                }
            }

            // Submit documents through the main KYC service
            $this->kycService->submitKyc($user, $request->documents);

            // Create verification request if investment is specified
            if (isset($investment)) {
                $requirements = $this->cgoKycService->checkKycRequirements($investment);
                $this->cgoKycService->createVerificationRequest($investment, $requirements['required_level']);
            }

            DB::commit();

            return response()->json(
                [
                    'success' => true,
                    'message' => 'KYC documents submitted successfully',
                    'data'    => [
                        'documents_submitted' => count($request->documents),
                        'status'              => 'pending_review',
                    ],
                ]
            );
        } catch (Exception $e) {
            DB::rollBack();
            Log::error(
                'CGO KYC submission failed',
                [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]
            );

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Failed to submit KYC documents',
                ],
                500
            );
        }
    }

    /**
     * Get KYC documents for current user.
     */
    public function documents()
    {
        $user = Auth::user();
        /** @var User $user */
        $documents = $user->kycDocuments()
            ->select('id', 'document_type', 'status', 'uploaded_at', 'verified_at', 'expires_at', 'rejection_reason')
            ->orderBy('uploaded_at', 'desc')
            ->get()
            ->map(
                function ($doc) {
                    return [
                        'id'               => $doc->id,
                        'type'             => $doc->document_type,
                        'type_label'       => KycDocument::DOCUMENT_TYPES[$doc->document_type] ?? $doc->document_type,
                        'status'           => $doc->status,
                        'uploaded_at'      => $doc->uploaded_at,
                        'verified_at'      => $doc->verified_at,
                        'expires_at'       => $doc->expires_at,
                        'rejection_reason' => $doc->rejection_reason,
                        'is_expired'       => $doc->isExpired(),
                    ];
                }
            );

        return response()->json(
            [
                'success' => true,
                'data'    => $documents,
            ]
        );
    }

    /**
     * Verify KYC for a specific investment.
     */
    public function verifyInvestment(Request $request, $investmentUuid)
    {
        $user = Auth::user();
        /** @var User $user */
        $investment = CgoInvestment::where('uuid', $investmentUuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($investment->status !== 'kyc_required' && $investment->status !== 'pending') {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Investment is not pending KYC verification',
                ],
                400
            );
        }

        $verified = $this->cgoKycService->verifyInvestor($investment);

        if ($verified) {
            return response()->json(
                [
                    'success' => true,
                    'message' => 'KYC verification successful',
                    'data'    => [
                        'investment_id' => $investment->uuid,
                        'kyc_level'     => $investment->kyc_level,
                        'can_proceed'   => true,
                    ],
                ]
            );
        } else {
            $requirements = $this->cgoKycService->checkKycRequirements($investment);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'KYC verification required',
                    'data'    => [
                        'investment_id'      => $investment->uuid,
                        'required_level'     => $requirements['required_level'],
                        'current_level'      => $requirements['current_level'],
                        'required_documents' => $requirements['required_documents'],
                        'status'             => $investment->status,
                    ],
                ],
                422
            );
        }
    }

    /**
     * Get investment limits based on KYC level.
     */
    protected function getInvestmentLimits($user): array
    {
        $requirements = $this->kycService->getRequirements($user->kyc_level ?: 'none');

        return [
            'current_level'           => $user->kyc_level ?: 'none',
            'single_investment_limit' => $requirements['limits']['daily_transaction'] ?? 0,
            'total_investment_limit'  => $requirements['limits']['max_balance'] ?? 0,
            'available_limit'         => $this->calculateAvailableLimit($user, $requirements['limits']['max_balance'] ?? 0),
        ];
    }

    /**
     * Calculate available investment limit.
     */
    protected function calculateAvailableLimit($user, $maxLimit): ?float
    {
        if (! $maxLimit) {
            return null; // No limit
        }

        $totalInvested = CgoInvestment::where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->sum('amount');

        return max(0, $maxLimit - $totalInvested);
    }

    /**
     * Get pending documents for user.
     */
    protected function getPendingDocuments($user): array
    {
        return $user->kycDocuments()
            ->where('status', 'pending')
            ->pluck('document_type')
            ->toArray();
    }

    /**
     * Get required actions for user.
     */
    protected function getRequiredActions($user): array
    {
        $actions = [];

        // Check if KYC is expired
        if ($user->kyc_expires_at && $user->kyc_expires_at->isPast()) {
            $actions[] = [
                'type'     => 'renew_kyc',
                'message'  => 'Your KYC verification has expired. Please submit updated documents.',
                'priority' => 'high',
            ];
        }

        // Check if documents are expiring soon
        $expiringDocs = $user->kycDocuments()
            ->where('status', 'verified')
            ->where('expires_at', '<', now()->addDays(30))
            ->get();

        foreach ($expiringDocs as $doc) {
            $actions[] = [
                'type'          => 'update_document',
                'message'       => "Your {$doc->document_type} is expiring soon. Please submit an updated document.",
                'document_type' => $doc->document_type,
                'expires_at'    => $doc->expires_at,
                'priority'      => 'medium',
            ];
        }

        // Check if additional documents needed for higher investment
        if ($user->kyc_level === 'basic') {
            $totalInvested = CgoInvestment::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'pending'])
                ->sum('amount');

            if ($totalInvested > 800) { // Getting close to basic limit
                $actions[] = [
                    'type'     => 'upgrade_kyc',
                    'message'  => 'You are approaching your investment limit. Upgrade to Enhanced KYC to invest more.',
                    'priority' => 'low',
                ];
            }
        }

        return $actions;
    }
}
