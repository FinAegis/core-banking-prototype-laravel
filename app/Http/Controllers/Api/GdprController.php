<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Compliance\Services\GdprService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class GdprController extends Controller
{
    public function __construct(
        private readonly GdprService $gdprService
    ) {}

    /**
     * Get user's consent status
     */
    public function consentStatus(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'consents' => [
                'privacy_policy' => $user->privacy_policy_accepted_at !== null,
                'terms' => $user->terms_accepted_at !== null,
                'marketing' => $user->marketing_consent_at !== null,
                'data_retention' => $user->data_retention_consent,
            ],
            'dates' => [
                'privacy_policy_accepted_at' => $user->privacy_policy_accepted_at,
                'terms_accepted_at' => $user->terms_accepted_at,
                'marketing_consent_at' => $user->marketing_consent_at,
            ],
        ]);
    }

    /**
     * Update user's consent preferences
     */
    public function updateConsent(Request $request): JsonResponse
    {
        $request->validate([
            'privacy_policy' => 'sometimes|boolean',
            'terms' => 'sometimes|boolean',
            'marketing' => 'sometimes|boolean',
            'data_retention' => 'sometimes|boolean',
        ]);

        $user = Auth::user();
        $this->gdprService->updateConsent($user, $request->all());

        return response()->json([
            'message' => 'Consent preferences updated successfully',
        ]);
    }

    /**
     * Request data export (GDPR Article 20)
     */
    public function requestDataExport(): JsonResponse
    {
        $user = Auth::user();

        try {
            $data = $this->gdprService->exportUserData($user);

            // In a real application, this would queue a job to generate
            // and email the export to the user
            return response()->json([
                'message' => 'Data export requested. You will receive an email with your data shortly.',
                'preview' => [
                    'sections' => array_keys($data),
                    'generated_at' => now(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process data export request',
            ], 500);
        }
    }

    /**
     * Request account deletion (GDPR Article 17)
     */
    public function requestDeletion(Request $request): JsonResponse
    {
        $request->validate([
            'confirm' => 'required|boolean|accepted',
            'reason' => 'sometimes|string|max:500',
        ]);

        $user = Auth::user();

        // Check if deletion is allowed
        $check = $this->gdprService->canDeleteUserData($user);
        if (!$check['can_delete']) {
            return response()->json([
                'error' => 'Account cannot be deleted at this time',
                'reasons' => $check['reasons'],
            ], 400);
        }

        try {
            // In a real application, this would queue a job and require
            // additional confirmation steps
            $this->gdprService->deleteUserData($user, [
                'reason' => $request->reason,
                'delete_documents' => true,
                'anonymize_transactions' => true,
            ]);

            return response()->json([
                'message' => 'Account deletion request processed. Your account will be deleted within 30 days.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process deletion request',
            ], 500);
        }
    }

    /**
     * Get data retention policy
     */
    public function retentionPolicy(): JsonResponse
    {
        return response()->json([
            'policy' => [
                'transaction_data' => '7 years (regulatory requirement)',
                'kyc_documents' => '5 years after account closure',
                'audit_logs' => '3 years',
                'marketing_data' => 'Until consent withdrawn',
                'inactive_accounts' => 'Deleted after 2 years of inactivity',
            ],
            'user_rights' => [
                'access' => 'You can request a copy of your data at any time',
                'rectification' => 'You can update your personal information',
                'erasure' => 'You can request deletion (subject to legal requirements)',
                'portability' => 'You can export your data in machine-readable format',
                'object' => 'You can object to certain processing activities',
            ],
        ]);
    }
}