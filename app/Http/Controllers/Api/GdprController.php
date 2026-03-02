<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Compliance\Services\GdprService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'GDPR',
    description: 'General Data Protection Regulation (GDPR) compliance operations'
)]
class GdprController extends Controller
{
    public function __construct(
        private readonly GdprService $gdprService
    ) {
    }

        #[OA\Get(
            path: '/api/gdpr/consent-status',
            operationId: 'getGdprConsentStatus',
            tags: ['GDPR'],
            summary: 'Get user\'s consent status',
            description: 'Retrieve the current consent status for various data processing activities',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'consents', type: 'object', properties: [
        new OA\Property(property: 'privacy_policy', type: 'boolean', example: true),
        new OA\Property(property: 'terms', type: 'boolean', example: true),
        new OA\Property(property: 'marketing', type: 'boolean', example: false),
        new OA\Property(property: 'data_retention', type: 'boolean', example: true),
        ]),
        new OA\Property(property: 'dates', type: 'object', properties: [
        new OA\Property(property: 'privacy_policy_accepted_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'terms_accepted_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'marketing_consent_at', type: 'string', format: 'date-time', nullable: true),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function consentStatus(): JsonResponse
    {
        $user = Auth::user();
        /** @var User $user */

        return response()->json(
            [
                'consents' => [
                    'privacy_policy' => $user->privacy_policy_accepted_at !== null,
                    'terms'          => $user->terms_accepted_at !== null,
                    'marketing'      => $user->marketing_consent_at !== null,
                    'data_retention' => $user->data_retention_consent,
                ],
                'dates' => [
                    'privacy_policy_accepted_at' => $user->privacy_policy_accepted_at,
                    'terms_accepted_at'          => $user->terms_accepted_at,
                    'marketing_consent_at'       => $user->marketing_consent_at,
                ],
            ]
        );
    }

        #[OA\Post(
            path: '/api/gdpr/consent',
            operationId: 'updateGdprConsent',
            tags: ['GDPR'],
            summary: 'Update user\'s consent preferences',
            description: 'Update consent preferences for various data processing activities',
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'privacy_policy', type: 'boolean', example: true),
        new OA\Property(property: 'terms', type: 'boolean', example: true),
        new OA\Property(property: 'marketing', type: 'boolean', example: false),
        new OA\Property(property: 'data_retention', type: 'boolean', example: true),
        ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Consent updated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Consent preferences updated successfully'),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
    )]
    public function updateConsent(Request $request): JsonResponse
    {
        $request->validate(
            [
                'privacy_policy' => 'sometimes|boolean',
                'terms'          => 'sometimes|boolean',
                'marketing'      => 'sometimes|boolean',
                'data_retention' => 'sometimes|boolean',
            ]
        );

        $user = Auth::user();
        /** @var User $user */
        $this->gdprService->updateConsent($user, $request->all());

        return response()->json(
            [
                'message' => 'Consent preferences updated successfully',
            ]
        );
    }

        #[OA\Post(
            path: '/api/gdpr/export',
            operationId: 'requestGdprDataExport',
            tags: ['GDPR'],
            summary: 'Request data export (GDPR Article 20)',
            description: 'Request a complete export of all personal data in a machine-readable format. Returns 202 and processes asynchronously.',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 202,
        description: 'Export request accepted for processing',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Data export request accepted. Check status at the provided URL.'),
        new OA\Property(property: 'export_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'status_url', type: 'string', example: '/api/gdpr/export/550e8400-e29b-41d4-a716-446655440000'),
        ])
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Failed to process data export request'),
        ])
    )]
    public function requestDataExport(): JsonResponse
    {
        $user = Auth::user();
        /** @var User $user */
        try {
            $exportId = (string) \Illuminate\Support\Str::uuid();

            \App\Jobs\ProcessGdprDataExport::dispatch($user->id, $exportId);

            return response()->json(
                [
                    'message'    => 'Data export request accepted. Check status at the provided URL.',
                    'export_id'  => $exportId,
                    'status_url' => "/api/gdpr/export/{$exportId}",
                ],
                202
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => 'Failed to process data export request',
                ],
                500
            );
        }
    }

    #[OA\Get(
        path: '/api/gdpr/export/{exportId}',
        operationId: 'getGdprExportStatus',
        tags: ['GDPR'],
        summary: 'Check data export status',
        description: 'Poll the status of a previously requested data export',
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'exportId', in: 'path', required: true, description: 'Export request ID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Export status',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'export_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'completed', 'failed']),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Export request not found'
    )]
    public function getExportStatus(string $exportId): JsonResponse
    {
        $cacheKey = "gdpr_export:{$exportId}";
        $status = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if ($status === null) {
            return response()->json([
                'error' => 'Export request not found or expired',
            ], 404);
        }

        $response = array_merge(
            ['export_id' => $exportId],
            (array) $status,
        );

        // Regenerate a fresh signed download URL if export is completed
        if (($response['status'] ?? '') === 'completed' && ! empty($response['file_path'])) {
            $response['download_url'] = \Illuminate\Support\Facades\URL::signedRoute(
                'api.user.data-export.download',
                ['exportId' => $exportId],
                now()->addHour(),
            );
            unset($response['file_path']);
        }

        return response()->json($response);
    }

    #[OA\Get(
        path: '/api/v1/user/data-export/{exportId}/download',
        operationId: 'downloadGdprExport',
        tags: ['GDPR'],
        summary: 'Download completed GDPR data export',
        description: 'Download the exported data as a JSON file. Requires a valid signed URL.',
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'exportId', in: 'path', required: true, description: 'Export request ID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'JSON file download',
        content: new OA\JsonContent(type: 'object')
    )]
    #[OA\Response(
        response: 403,
        description: 'Invalid or expired signature'
    )]
    #[OA\Response(
        response: 404,
        description: 'Export file not found'
    )]
    public function downloadExport(Request $request, string $exportId): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['error' => 'Invalid or expired download link.'], 403);
        }

        $cacheKey = "gdpr_export:{$exportId}";
        $status = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if ($status === null || ($status['status'] ?? '') !== 'completed') {
            return response()->json(['error' => 'Export not found or not yet completed.'], 404);
        }

        $filePath = $status['file_path'] ?? "gdpr-exports/{$exportId}.json.enc";

        if (! Storage::disk('local')->exists($filePath)) {
            return response()->json(['error' => 'Export file not found or expired.'], 404);
        }

        $decrypted = decrypt((string) Storage::disk('local')->get($filePath));

        return response($decrypted, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"gdpr-export-{$exportId}.json\"",
        ]);
    }

        #[OA\Post(
            path: '/api/gdpr/delete',
            operationId: 'requestGdprAccountDeletion',
            tags: ['GDPR'],
            summary: 'Request account deletion (GDPR Article 17)',
            description: 'Request complete deletion of account and personal data (right to be forgotten)',
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['confirm'], properties: [
        new OA\Property(property: 'confirm', type: 'boolean', example: true, description: 'Confirmation of deletion request'),
        new OA\Property(property: 'reason', type: 'string', maxLength: 500, example: 'No longer using the service'),
        ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Deletion request successful',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Account deletion request processed. Your account will be deleted within 30 days.'),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Deletion not allowed',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Account cannot be deleted at this time'),
        new OA\Property(property: 'reasons', type: 'array', example: ['Outstanding balance', 'Active loans'], items: new OA\Items(type: 'string')),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function requestDeletion(Request $request): JsonResponse
    {
        $request->validate(
            [
                'confirm' => 'required|boolean|accepted',
                'reason'  => 'sometimes|string|max:500',
            ]
        );

        $user = Auth::user();
        /** @var User $user */

        // Check if deletion is allowed
        $check = $this->gdprService->canDeleteUserData($user);
        if (! $check['can_delete']) {
            return response()->json(
                [
                    'error'   => 'Account cannot be deleted at this time',
                    'reasons' => $check['reasons'],
                ],
                400
            );
        }

        try {
            // In a real application, this would queue a job and require
            // additional confirmation steps
            $this->gdprService->deleteUserData(
                $user,
                [
                    'reason'                 => $request->reason,
                    'delete_documents'       => true,
                    'anonymize_transactions' => true,
                ]
            );

            return response()->json(
                [
                    'message' => 'Account deletion request processed. Your account will be deleted within 30 days.',
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => 'Failed to process deletion request',
                ],
                500
            );
        }
    }

        #[OA\Get(
            path: '/api/gdpr/retention-policy',
            operationId: 'getGdprRetentionPolicy',
            tags: ['GDPR'],
            summary: 'Get data retention policy',
            description: 'Retrieve information about data retention periods and user rights under GDPR'
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'policy', type: 'object', properties: [
        new OA\Property(property: 'transaction_data', type: 'string', example: '7 years (regulatory requirement)'),
        new OA\Property(property: 'kyc_documents', type: 'string', example: '5 years after account closure'),
        new OA\Property(property: 'audit_logs', type: 'string', example: '3 years'),
        new OA\Property(property: 'marketing_data', type: 'string', example: 'Until consent withdrawn'),
        new OA\Property(property: 'inactive_accounts', type: 'string', example: 'Deleted after 2 years of inactivity'),
        ]),
        new OA\Property(property: 'user_rights', type: 'object', properties: [
        new OA\Property(property: 'access', type: 'string', example: 'You can request a copy of your data at any time'),
        new OA\Property(property: 'rectification', type: 'string', example: 'You can update your personal information'),
        new OA\Property(property: 'erasure', type: 'string', example: 'You can request deletion (subject to legal requirements)'),
        new OA\Property(property: 'portability', type: 'string', example: 'You can export your data in machine-readable format'),
        new OA\Property(property: 'object', type: 'string', example: 'You can object to certain processing activities'),
        ]),
        ])
    )]
    public function retentionPolicy(): JsonResponse
    {
        return response()->json(
            [
                'policy' => [
                    'transaction_data'  => '7 years (regulatory requirement)',
                    'kyc_documents'     => '5 years after account closure',
                    'audit_logs'        => '3 years',
                    'marketing_data'    => 'Until consent withdrawn',
                    'inactive_accounts' => 'Deleted after 2 years of inactivity',
                ],
                'user_rights' => [
                    'access'        => 'You can request a copy of your data at any time',
                    'rectification' => 'You can update your personal information',
                    'erasure'       => 'You can request deletion (subject to legal requirements)',
                    'portability'   => 'You can export your data in machine-readable format',
                    'object'        => 'You can object to certain processing activities',
                ],
            ]
        );
    }
}
