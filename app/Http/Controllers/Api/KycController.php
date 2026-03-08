<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Compliance\Models\KycVerification;
use App\Domain\Compliance\Services\KycService;
use App\Domain\Compliance\Services\OndatoService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'KYC',
    description: 'Know Your Customer (KYC) verification operations'
)]
class KycController extends Controller
{
    public function __construct(
        private readonly KycService $kycService
    ) {
    }

        #[OA\Get(
            path: '/api/kyc/status',
            operationId: 'getKycStatus',
            tags: ['KYC'],
            summary: 'Get KYC status for authenticated user',
            description: 'Retrieve the current KYC verification status and documents for the authenticated user',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['unverified', 'pending', 'approved', 'rejected'], example: 'pending'),
        new OA\Property(property: 'level', type: 'string', enum: ['basic', 'enhanced', 'full'], example: 'enhanced'),
        new OA\Property(property: 'submitted_at', type: 'string', format: 'date-time', example: '2025-01-15T10:00:00Z'),
        new OA\Property(property: 'approved_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'needs_kyc', type: 'boolean', example: true),
        new OA\Property(property: 'documents', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string', example: '123'),
        new OA\Property(property: 'type', type: 'string', example: 'passport'),
        new OA\Property(property: 'status', type: 'string', example: 'approved'),
        new OA\Property(property: 'uploaded_at', type: 'string', format: 'date-time'),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function status(): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = Auth::user();
        /** @var User $user */

        return response()->json(
            [
                'status'       => $user->kyc_status,
                'level'        => $user->kyc_level,
                'submitted_at' => $user->kyc_submitted_at,
                'approved_at'  => $user->kyc_approved_at,
                'expires_at'   => $user->kyc_expires_at,
                'needs_kyc'    => $user->needsKyc(),
                'documents'    => $user->kycDocuments->map(
                    fn ($doc) => [
                        'id'          => $doc->id,
                        'type'        => $doc->document_type,
                        'status'      => $doc->status,
                        'uploaded_at' => $doc->uploaded_at,
                    ]
                ),
            ]
        );
    }

        #[OA\Get(
            path: '/api/kyc/requirements',
            operationId: 'getKycRequirements',
            tags: ['KYC'],
            summary: 'Get KYC requirements for a level',
            description: 'Retrieve the document requirements for a specific KYC verification level',
            parameters: [
            new OA\Parameter(name: 'level', in: 'query', description: 'KYC verification level', required: true, schema: new OA\Schema(type: 'string', enum: ['basic', 'enhanced', 'full'])),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'level', type: 'string', example: 'enhanced'),
        new OA\Property(property: 'requirements', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'document_type', type: 'string', example: 'passport'),
        new OA\Property(property: 'description', type: 'string', example: 'Valid passport copy'),
        new OA\Property(property: 'required', type: 'boolean', example: true),
        ])),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
    )]
    public function requirements(Request $request): JsonResponse
    {
        $request->validate(
            [
                'level' => 'required|in:basic,enhanced,full',
            ]
        );

        $requirements = $this->kycService->getRequirements($request->level);

        return response()->json(
            [
                'level'        => $request->level,
                'requirements' => $requirements,
            ]
        );
    }

        #[OA\Post(
            path: '/api/kyc/submit',
            operationId: 'submitKycDocuments',
            tags: ['KYC'],
            summary: 'Submit KYC documents',
            description: 'Submit KYC verification documents for review',
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
                properties: [
                new OA\Property(
                    property: 'documents',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                        new OA\Property(property: 'type', type: 'string', enum: ['passport', 'national_id', 'drivers_license', 'residence_permit', 'utility_bill', 'bank_statement', 'selfie', 'proof_of_income', 'other']),
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Document file (jpg, jpeg, png, pdf - max 10MB)'),
                        ]
                    )
                ),
                ]
            )))
        )]
    #[OA\Response(
        response: 200,
        description: 'Documents submitted successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'KYC documents submitted successfully'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request - KYC already approved',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'error', type: 'string', example: 'KYC already approved'),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Failed to submit KYC documents'),
        ])
    )]
    public function submit(Request $request): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = Auth::user();
        /** @var User $user */
        if ($user->kyc_status === 'approved') {
            return response()->json(
                [
                    'error' => 'KYC already approved',
                ],
                400
            );
        }

        $request->validate(
            [
                'documents'        => 'required|array|min:1',
                'documents.*.type' => 'required|in:passport,national_id,drivers_license,residence_permit,utility_bill,bank_statement,selfie,proof_of_income,other',
                'documents.*.file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB max
            ]
        );

        try {
            $this->kycService->submitKyc($user, $request->documents);

            return response()->json(
                [
                    'message' => 'KYC documents submitted successfully',
                    'status'  => 'pending',
                ]
            );
        } catch (Exception $e) {
            AuditLog::log(
                'kyc.submission_failed',
                null,
                null,
                null,
                ['error' => $e->getMessage(), 'user_uuid' => $user->uuid],
                'kyc,error'
            );

            return response()->json(
                [
                    'error' => 'Failed to submit KYC documents',
                ],
                500
            );
        }
    }

        #[OA\Get(
            path: '/api/kyc/documents/{documentId}/download',
            operationId: 'downloadKycDocument',
            tags: ['KYC'],
            summary: 'Download a KYC document',
            description: 'Download a previously uploaded KYC document. Users can only download their own documents.',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'documentId', in: 'path', description: 'The document ID', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Document file download',
        content: new OA\MediaType(
            mediaType: 'application/octet-stream',
            schema: new OA\Schema(type: 'string', format: 'binary')
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Document not found'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    public function downloadDocument(string $documentId): mixed
    {
        /**
         * @var User $user
         */
        $user = Auth::user();
        /** @var User $user */
        $document = $user->kycDocuments()->findOrFail($documentId);

        if (! Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'Document not found');
        }

        AuditLog::log(
            'kyc.document_downloaded',
            $document,
            null,
            null,
            null,
            'kyc,document'
        );

        return Storage::disk('private')->download(
            $document->file_path,
            $document->metadata['original_name'] ?? 'document'
        );
    }

    /**
     * Upload KYC document (legacy endpoint for backward compatibility).
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate(
            [
                'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
                'type'     => 'sometimes|string|in:passport,national_id,drivers_license,residence_permit,utility_bill,bank_statement,selfie,proof_of_income,other',
            ]
        );

        /**
         * @var User $user
         */
        $user = Auth::user();
        /** @var User $user */
        $file = $request->file('document');
        $type = $request->input('type', 'other');

        try {
            // Store the document
            $path = $file->store('kyc/' . $user->uuid, 'private');

            // Create document record
            $document = $user->kycDocuments()->create(
                [
                    'document_type' => $type,
                    'file_path'     => $path,
                    'status'        => 'pending',
                    'uploaded_at'   => now(),
                    'metadata'      => [
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type'     => $file->getMimeType(),
                        'size'          => $file->getSize(),
                    ],
                ]
            );

            AuditLog::log(
                'kyc.document_uploaded',
                $document,
                null,
                null,
                ['document_type' => $type],
                'kyc,document'
            );

            return response()->json(
                [
                    'message'     => 'Document uploaded successfully',
                    'document_id' => $document->id,
                ]
            );
        } catch (Exception $e) {
            AuditLog::log(
                'kyc.upload_failed',
                null,
                null,
                null,
                ['error' => $e->getMessage(), 'user_uuid' => $user->uuid],
                'kyc,error'
            );

            return response()->json(
                [
                    'error' => 'Failed to upload document',
                ],
                500
            );
        }
    }

    /**
     * Start Ondato KYC verification for the authenticated user.
     */
    #[OA\Post(
        path: '/api/compliance/kyc/ondato/start',
        operationId: 'startOndatoVerification',
        tags: ['KYC'],
        summary: 'Start Ondato identity verification',
        description: 'Creates an Ondato identity verification session linked to a TrustCert application',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'application_id', type: 'string', example: 'app_abc123', description: 'TrustCertApplication ID'),
        new OA\Property(property: 'target_level', type: 'integer', example: 2, description: 'Trust level (0-3)'),
        new OA\Property(property: 'first_name', type: 'string', example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Verification session created',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'identity_verification_id', type: 'string', example: '3fa85f64-5717-4562-b3fc-2c963f66afa6'),
        new OA\Property(property: 'verification_id', type: 'string', example: '9c1a2b3d-4e5f-6789-abcd-ef0123456789'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'KYC already approved or invalid application',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'error', type: 'string', example: 'KYC already approved'),
        ])
    )]
    #[OA\Response(
        response: 500,
        description: 'Failed to start verification'
    )]
    public function startOndatoVerification(Request $request, OndatoService $ondatoService): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->kyc_status === 'approved') {
            return response()->json(['error' => 'KYC already approved'], 400);
        }

        $request->validate([
            'application_id' => 'required|string',
            'target_level'   => 'required|integer|between:0,3',
            'first_name'     => 'sometimes|string|max:255',
            'last_name'      => 'sometimes|string|max:255',
        ]);

        // Verify the TrustCert application exists and is valid
        $application = Cache::get("trustcert_application:{$user->id}");
        if (! $application || ($application['id'] ?? null) !== $request->input('application_id')) {
            return response()->json(['error' => 'TrustCert application not found'], 400);
        }

        if (in_array($application['status'] ?? '', ['approved', 'cancelled'], true)) {
            return response()->json(['error' => 'TrustCert application is already ' . $application['status']], 400);
        }

        // Map integer target_level to string enum
        $targetLevelMap = [0 => 'unknown', 1 => 'basic', 2 => 'verified', 3 => 'high'];
        $targetLevelString = $targetLevelMap[(int) $request->input('target_level')] ?? 'unknown';

        try {
            $data = $request->only(['first_name', 'last_name']);
            $data['application_id'] = $request->input('application_id');
            $data['target_level'] = $targetLevelString;

            $result = $ondatoService->createIdentityVerification($user, $data);

            return response()->json(['data' => $result]);
        } catch (Exception $e) {
            AuditLog::log(
                'kyc.ondato_start_failed',
                null,
                null,
                null,
                ['error' => $e->getMessage(), 'user_uuid' => $user->uuid],
                'kyc,ondato,error'
            );

            return response()->json(['error' => 'Failed to start Ondato verification'], 500);
        }
    }

    /**
     * Get Ondato verification status for the authenticated user.
     */
    #[OA\Get(
        path: '/api/compliance/kyc/ondato/status/{verificationId}',
        operationId: 'getOndatoVerificationStatus',
        tags: ['KYC'],
        summary: 'Get Ondato verification status',
        description: 'Retrieve the status of a specific Ondato KYC verification',
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'verificationId', in: 'path', required: true, description: 'The KycVerification ID', schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Verification status',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'verification_id', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'completed', 'failed', 'expired']),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'failure_reason', type: 'string', nullable: true),
        new OA\Property(property: 'trust_cert_level', type: 'integer', nullable: true, description: 'Trust level (1-3) when completed'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Verification not found'
    )]
    public function getOndatoVerificationStatus(string $verificationId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $verification = KycVerification::where('id', $verificationId)
            ->where('user_id', $user->id)
            ->where('provider', 'ondato')
            ->firstOrFail();

        // Map target_level string to integer for the response
        $trustCertLevel = null;
        if ($verification->status === KycVerification::STATUS_COMPLETED && $verification->target_level) {
            $levelMap = ['basic' => 1, 'verified' => 2, 'high' => 3];
            $trustCertLevel = $levelMap[$verification->target_level] ?? null;
        }

        return response()->json(['data' => [
            'verification_id'  => $verification->id,
            'status'           => $verification->status,
            'completed_at'     => $verification->completed_at?->toIso8601String(),
            'failure_reason'   => $verification->failure_reason,
            'trust_cert_level' => $trustCertLevel,
        ]]);
    }
}
