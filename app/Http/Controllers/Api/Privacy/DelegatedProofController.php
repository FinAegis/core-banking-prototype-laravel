<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Privacy;

use App\Domain\Privacy\Exceptions\DelegatedProofException;
use App\Domain\Privacy\Services\DelegatedProofService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;
use Throwable;

/**
 * @OA\Tag(
 *     name="Delegated Proofs",
 *     description="Server-side ZK proof generation for low-end devices"
 * )
 */
class DelegatedProofController extends Controller
{
    public function __construct(
        private readonly DelegatedProofService $proofService,
    ) {
    }

    /**
     * Request a new delegated proof generation.
     *
     * @OA\Post(
     *     path="/api/v1/privacy/delegated-proof",
     *     summary="Request delegated proof generation",
     *     description="Submit a proof generation request to be processed server-side",
     *     tags={"Delegated Proofs"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"proof_type", "network", "public_inputs", "encrypted_private_inputs"},
     *             @OA\Property(property="proof_type", type="string", enum={"shield_1_1", "unshield_2_1", "transfer_2_2", "proof_of_innocence"}),
     *             @OA\Property(property="network", type="string", enum={"polygon", "base", "arbitrum"}),
     *             @OA\Property(property="public_inputs", type="object", description="Public inputs for the proof"),
     *             @OA\Property(property="encrypted_private_inputs", type="string", description="Encrypted private inputs")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Proof job created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="job_id", type="string", format="uuid"),
     *                 @OA\Property(property="status", type="string", example="queued"),
     *                 @OA\Property(property="estimated_seconds", type="integer", example=30)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid request"),
     *     @OA\Response(response=429, description="Too many pending jobs")
     * )
     */
    public function requestProof(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'proof_type'               => 'required|string|in:shield_1_1,unshield_2_1,transfer_2_2,proof_of_innocence',
            'network'                  => 'required|string|in:polygon,base,arbitrum',
            'public_inputs'            => 'required|array',
            'encrypted_private_inputs' => 'required|string',
        ]);

        try {
            /** @var User $user */
            $user = $request->user();

            $job = $this->proofService->requestProof(
                $user,
                $validated['proof_type'],
                $validated['network'],
                $validated['public_inputs'],
                $validated['encrypted_private_inputs']
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'job_id'            => $job->id,
                    'status'            => $job->status,
                    'estimated_seconds' => $job->estimated_seconds,
                ],
            ]);
        } catch (DelegatedProofException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => $e->errorCode,
                    'message' => $e->getMessage(),
                ],
            ], $e->httpStatusCode);
        } catch (Throwable $e) {
            Log::error('Delegated proof request failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_PRIVACY_300',
                    'message' => 'Failed to create proof job',
                ],
            ], 500);
        }
    }

    /**
     * Get proof job status.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/delegated-proof/{jobId}",
     *     summary="Get proof job status",
     *     description="Check the status of a delegated proof generation job",
     *     tags={"Delegated Proofs"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="jobId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Proof job status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="proof_type", type="string"),
     *                 @OA\Property(property="network", type="string"),
     *                 @OA\Property(property="status", type="string", enum={"queued", "proving", "completed", "failed"}),
     *                 @OA\Property(property="progress", type="integer", example=50),
     *                 @OA\Property(property="proof", type="string", nullable=true),
     *                 @OA\Property(property="error", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Job not found")
     * )
     */
    public function getProofStatus(Request $request, string $jobId): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            $job = $this->proofService->getJob($user, $jobId);

            return response()->json([
                'success' => true,
                'data'    => $job->toApiResponse(),
            ]);
        } catch (DelegatedProofException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => $e->errorCode,
                    'message' => $e->getMessage(),
                ],
            ], $e->httpStatusCode);
        }
    }

    /**
     * List user's proof jobs.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/delegated-proofs",
     *     summary="List proof jobs",
     *     description="List all delegated proof jobs for the authenticated user",
     *     tags={"Delegated Proofs"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"queued", "proving", "completed", "failed"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of proof jobs",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function listProofJobs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:queued,proving,completed,failed',
        ]);

        /** @var User $user */
        $user = $request->user();

        $jobs = $this->proofService->getUserJobs($user, $validated['status'] ?? null);

        return response()->json([
            'success' => true,
            'data'    => $jobs->map(fn ($job) => $job->toApiResponse()),
        ]);
    }

    /**
     * Cancel a pending proof job.
     *
     * @OA\Delete(
     *     path="/api/v1/privacy/delegated-proof/{jobId}",
     *     summary="Cancel proof job",
     *     description="Cancel a pending or in-progress proof job",
     *     tags={"Delegated Proofs"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="jobId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Job cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Proof job cancelled")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Cannot cancel job"),
     *     @OA\Response(response=404, description="Job not found")
     * )
     */
    public function cancelProofJob(Request $request, string $jobId): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            $this->proofService->cancelJob($user, $jobId);

            return response()->json([
                'success' => true,
                'message' => 'Proof job cancelled',
            ]);
        } catch (DelegatedProofException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => $e->errorCode,
                    'message' => $e->getMessage(),
                ],
            ], $e->httpStatusCode);
        }
    }

    /**
     * Get supported proof types and networks.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/delegated-proof-types",
     *     summary="Get supported proof types",
     *     description="Get list of supported proof types and networks for delegated proving",
     *     tags={"Delegated Proofs"},
     *     @OA\Response(
     *         response=200,
     *         description="Supported proof types",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="proof_types", type="object"),
     *                 @OA\Property(property="networks", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function getSupportedTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'proof_types' => $this->proofService->getSupportedProofTypes(),
                'networks'    => $this->proofService->getSupportedNetworks(),
            ],
        ]);
    }
}
