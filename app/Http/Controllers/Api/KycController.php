<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Compliance\Services\KycService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="KYC",
 *     description="Know Your Customer (KYC) verification operations"
 * )
 */
class KycController extends Controller
{
    public function __construct(
        private readonly KycService $kycService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/kyc/status",
     *     operationId="getKycStatus",
     *     tags={"KYC"},
     *     summary="Get KYC status for authenticated user",
     *     description="Retrieve the current KYC verification status and documents for the authenticated user",
     *     security={{"sanctum": {}}},
     *
     * @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="status",       type="string", enum={"unverified", "pending", "approved", "rejected"}, example="pending"),
     * @OA\Property(property="level",        type="string", enum={"basic", "enhanced", "full"}, example="enhanced"),
     * @OA\Property(property="submitted_at", type="string", format="date-time", example="2025-01-15T10:00:00Z"),
     * @OA\Property(property="approved_at",  type="string", format="date-time", nullable=true),
     * @OA\Property(property="expires_at",   type="string", format="date-time", nullable=true),
     * @OA\Property(property="needs_kyc",    type="boolean", example=true),
     * @OA\Property(property="documents",    type="array", @OA\Items(
     * @OA\Property(property="id",           type="string", example="123"),
     * @OA\Property(property="type",         type="string", example="passport"),
     * @OA\Property(property="status",       type="string", example="approved"),
     * @OA\Property(property="uploaded_at",  type="string", format="date-time")
     *             ))
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/kyc/requirements",
     *     operationId="getKycRequirements",
     *     tags={"KYC"},
     *     summary="Get KYC requirements for a level",
     *     description="Retrieve the document requirements for a specific KYC verification level",
     *
     * @OA\Parameter(
     *         name="level",
     *         in="query",
     *         description="KYC verification level",
     *         required=true,
     *
     * @OA\Schema(type="string",                                   enum={"basic", "enhanced", "full"})
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="level",                              type="string", example="enhanced"),
     * @OA\Property(property="requirements",                       type="array", @OA\Items(
     * @OA\Property(property="document_type",                      type="string", example="passport"),
     * @OA\Property(property="description",                        type="string", example="Valid passport copy"),
     * @OA\Property(property="required",                           type="boolean", example=true)
     *             ))
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     * @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/kyc/submit",
     *     operationId="submitKycDocuments",
     *     tags={"KYC"},
     *     summary="Submit KYC documents",
     *     description="Submit KYC verification documents for review",
     *     security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     * @OA\Schema(
     *
     * @OA\Property(
     *                     property="documents",
     *                     type="array",
     *
     * @OA\Items(
     *
     * @OA\Property(property="type",    type="string", enum={"passport", "national_id", "drivers_license", "residence_permit", "utility_bill", "bank_statement", "selfie", "proof_of_income", "other"}),
     * @OA\Property(property="file",    type="string", format="binary", description="Document file (jpg, jpeg, png, pdf - max 10MB)")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Documents submitted successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message", type="string", example="KYC documents submitted successfully"),
     * @OA\Property(property="status",  type="string", example="pending")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=400,
     *         description="Bad request - KYC already approved",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="error",   type="string", example="KYC already approved")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     * @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="error",   type="string", example="Failed to submit KYC documents")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/kyc/documents/{documentId}/download",
     *     operationId="downloadKycDocument",
     *     tags={"KYC"},
     *     summary="Download a KYC document",
     *     description="Download a previously uploaded KYC document. Users can only download their own documents.",
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="documentId",
     *         in="path",
     *         description="The document ID",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Document file download",
     *
     * @OA\MediaType(
     *             mediaType="application/octet-stream",
     *
     * @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Document not found"
     *     ),
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
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
}
