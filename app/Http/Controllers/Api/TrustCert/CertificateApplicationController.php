<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\TrustCert;

use App\Domain\TrustCert\Enums\TrustLevel;
use App\Domain\TrustCert\Services\CertificateAuthorityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CertificateApplicationController extends Controller
{
    public function __construct(
        private readonly CertificateAuthorityService $certificateAuthority,
    ) {
    }

    /**
     * Create a new certificate application.
     *
     * @OA\Post(
     *     path="/api/v1/trustcert/applications",
     *     operationId="trustCertApplicationCreate",
     *     summary="Create a new certificate application",
     *     description="Creates a new trust certificate application for the authenticated user. Only one active application is allowed at a time.",
     *     tags={"TrustCert"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"target_level"},
     *             @OA\Property(property="target_level", type="string", enum={"basic", "verified", "high", "ultimate"}, example="verified", description="The target trust level to apply for")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Application created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="app_abc123def456"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="target_level", type="string", example="verified"),
     *                 @OA\Property(property="status", type="string", example="draft"),
     *                 @OA\Property(property="requirements", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="documents", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="submitted_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="An active application already exists",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="APPLICATION_EXISTS"),
     *                 @OA\Property(property="message", type="string", example="An active application already exists.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'target_level' => ['required', 'string', 'in:basic,verified,high,ultimate'],
        ]);

        $user = $request->user();
        $targetLevel = TrustLevel::from($request->input('target_level'));

        // Check for existing active application
        $existing = $this->getActiveApplication($user->id);
        if ($existing) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'APPLICATION_EXISTS',
                    'message' => 'An active application already exists.',
                ],
            ], 409);
        }

        $application = [
            'id'           => 'app_' . Str::random(20),
            'user_id'      => $user->id,
            'target_level' => $targetLevel->value,
            'status'       => 'draft',
            'requirements' => $targetLevel->requirements(),
            'documents'    => [],
            'created_at'   => now()->toIso8601String(),
            'updated_at'   => now()->toIso8601String(),
            'submitted_at' => null,
        ];

        $this->storeApplication($user->id, $application);

        return response()->json([
            'success' => true,
            'data'    => $application,
        ], 201);
    }

    /**
     * Get a specific application by ID.
     *
     * @OA\Get(
     *     path="/api/v1/trustcert/applications/{id}",
     *     operationId="trustCertApplicationShow",
     *     summary="Get a specific certificate application",
     *     description="Retrieves a specific trust certificate application by its ID for the authenticated user.",
     *     tags={"TrustCert"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The application ID",
     *         @OA\Schema(type="string", example="app_abc123def456")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Application retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="app_abc123def456"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="target_level", type="string", example="verified"),
     *                 @OA\Property(property="status", type="string", example="draft"),
     *                 @OA\Property(property="requirements", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="documents", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="submitted_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Application not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="APPLICATION_NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="Application not found.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $application = $this->findApplication($user->id, $id);

        if (! $application) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'APPLICATION_NOT_FOUND',
                    'message' => 'Application not found.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $application,
        ]);
    }

    /**
     * Get the user's current active application.
     *
     * @OA\Get(
     *     path="/api/v1/trustcert/applications/current",
     *     operationId="trustCertApplicationCurrent",
     *     summary="Get current active certificate application",
     *     description="Returns the authenticated user's current active trust certificate application, or null if none exists.",
     *     tags={"TrustCert"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", nullable=true,
     *                 @OA\Property(property="id", type="string", example="app_abc123def456"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="target_level", type="string", example="verified"),
     *                 @OA\Property(property="status", type="string", example="draft"),
     *                 @OA\Property(property="requirements", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="documents", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="submitted_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function currentApplication(Request $request): JsonResponse
    {
        $user = $request->user();
        $application = $this->getActiveApplication($user->id);

        return response()->json([
            'success' => true,
            'data'    => $application,
        ]);
    }

    /**
     * Upload documents for a certificate application.
     *
     * @OA\Post(
     *     path="/api/v1/trustcert/applications/{id}/documents",
     *     operationId="trustCertApplicationUploadDocuments",
     *     summary="Upload documents for a certificate application",
     *     description="Uploads a document to the specified certificate application. The application must be in draft status.",
     *     tags={"TrustCert"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The application ID",
     *         @OA\Schema(type="string", example="app_abc123def456")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"document_type", "file_name"},
     *             @OA\Property(property="document_type", type="string", enum={"identity", "address", "kyc", "audit"}, example="identity", description="The type of document being uploaded"),
     *             @OA\Property(property="file_name", type="string", example="passport_scan.pdf", description="The name of the uploaded file")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Document uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="doc_abc123def456"),
     *                 @OA\Property(property="document_type", type="string", example="identity"),
     *                 @OA\Property(property="file_name", type="string", example="passport_scan.pdf"),
     *                 @OA\Property(property="uploaded_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Application not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="APPLICATION_NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="Application not found.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Application is not editable or validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="APPLICATION_NOT_EDITABLE"),
     *                 @OA\Property(property="message", type="string", example="Application is not in a draft state.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function uploadDocuments(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'document_type' => ['required', 'string', 'in:identity,address,kyc,audit'],
            'file_name'     => ['required', 'string'],
        ]);

        $user = $request->user();
        $application = $this->findApplication($user->id, $id);

        if (! $application) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'APPLICATION_NOT_FOUND',
                    'message' => 'Application not found.',
                ],
            ], 404);
        }

        if ($application['status'] !== 'draft') {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'APPLICATION_NOT_EDITABLE',
                    'message' => 'Application is not in a draft state.',
                ],
            ], 422);
        }

        $document = [
            'id'            => 'doc_' . Str::random(16),
            'document_type' => $request->input('document_type'),
            'file_name'     => $request->input('file_name'),
            'uploaded_at'   => now()->toIso8601String(),
        ];

        $application['documents'][] = $document;
        $application['updated_at'] = now()->toIso8601String();
        $this->storeApplication($user->id, $application);

        return response()->json([
            'success' => true,
            'data'    => $document,
        ], 201);
    }

    /**
     * Submit an application for review.
     *
     * @OA\Post(
     *     path="/api/v1/trustcert/applications/{id}/submit",
     *     operationId="trustCertApplicationSubmit",
     *     summary="Submit a certificate application for review",
     *     description="Submits a draft certificate application for review. Only applications in draft status can be submitted.",
     *     tags={"TrustCert"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The application ID",
     *         @OA\Schema(type="string", example="app_abc123def456")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Application submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="app_abc123def456"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="target_level", type="string", example="verified"),
     *                 @OA\Property(property="status", type="string", example="submitted"),
     *                 @OA\Property(property="requirements", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="documents", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="submitted_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Application not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="APPLICATION_NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="Application not found.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Application is not submittable",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="APPLICATION_NOT_SUBMITTABLE"),
     *                 @OA\Property(property="message", type="string", example="Only draft applications can be submitted.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function submit(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $application = $this->findApplication($user->id, $id);

        if (! $application) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'APPLICATION_NOT_FOUND',
                    'message' => 'Application not found.',
                ],
            ], 404);
        }

        if ($application['status'] !== 'draft') {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'APPLICATION_NOT_SUBMITTABLE',
                    'message' => 'Only draft applications can be submitted.',
                ],
            ], 422);
        }

        $application['status'] = 'submitted';
        $application['submitted_at'] = now()->toIso8601String();
        $application['updated_at'] = now()->toIso8601String();
        $this->storeApplication($user->id, $application);

        return response()->json([
            'success' => true,
            'data'    => $application,
        ]);
    }

    /**
     * Cancel a pending application.
     *
     * @OA\Post(
     *     path="/api/v1/trustcert/applications/{id}/cancel",
     *     operationId="trustCertApplicationCancel",
     *     summary="Cancel a certificate application",
     *     description="Cancels a pending certificate application. Applications that are already approved or cancelled cannot be cancelled.",
     *     tags={"TrustCert"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The application ID",
     *         @OA\Schema(type="string", example="app_abc123def456")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Application cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="app_abc123def456"),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="target_level", type="string", example="verified"),
     *                 @OA\Property(property="status", type="string", example="cancelled"),
     *                 @OA\Property(property="requirements", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="documents", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="submitted_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Application not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="APPLICATION_NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="Application not found.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Application cannot be cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="APPLICATION_NOT_CANCELLABLE"),
     *                 @OA\Property(property="message", type="string", example="This application cannot be cancelled.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function cancel(string $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $application = $this->findApplication($user->id, $id);

        if (! $application) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'APPLICATION_NOT_FOUND',
                    'message' => 'Application not found.',
                ],
            ], 404);
        }

        if (in_array($application['status'], ['approved', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'APPLICATION_NOT_CANCELLABLE',
                    'message' => 'This application cannot be cancelled.',
                ],
            ], 422);
        }

        $application['status'] = 'cancelled';
        $application['updated_at'] = now()->toIso8601String();
        $this->storeApplication($user->id, $application);

        return response()->json([
            'success' => true,
            'data'    => $application,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getActiveApplication(int $userId): ?array
    {
        $application = Cache::get("trustcert_application:{$userId}");

        if (! $application || in_array($application['status'], ['approved', 'cancelled'], true)) {
            return null;
        }

        return $application;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findApplication(int $userId, string $id): ?array
    {
        $application = Cache::get("trustcert_application:{$userId}");

        if (! $application || $application['id'] !== $id) {
            return null;
        }

        return $application;
    }

    /**
     * @param array<string, mixed> $application
     */
    private function storeApplication(int $userId, array $application): void
    {
        Cache::put("trustcert_application:{$userId}", $application, now()->addDays(30));
    }
}
