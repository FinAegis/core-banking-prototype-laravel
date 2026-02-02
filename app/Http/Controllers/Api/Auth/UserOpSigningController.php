<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Relayer\Contracts\UserOperationSignerInterface;
use App\Domain\Relayer\Exceptions\UserOpSigningException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for UserOperation signing with authentication shard.
 *
 * @OA\Tag(
 *     name="UserOp Signing",
 *     description="Sign ERC-4337 UserOperations with authentication shard"
 * )
 */
class UserOpSigningController extends Controller
{
    public function __construct(
        private readonly UserOperationSignerInterface $signingService,
    ) {
    }

    /**
     * Sign a UserOperation hash with the server's authentication shard.
     *
     * This endpoint is used by mobile apps to get the server's signature
     * for ERC-4337 UserOperations. The mobile app must provide:
     * - The UserOperation hash (computed client-side)
     * - A proof from the device's key shard
     * - A biometric authentication token
     *
     * The server validates the biometric token and signs with its auth shard.
     * The mobile app then combines both signatures for the final UserOp.
     *
     * @OA\Post(
     *     path="/api/v1/auth/sign-userop",
     *     operationId="signUserOperation",
     *     tags={"UserOp Signing"},
     *     summary="Sign a UserOperation with authentication shard",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_op_hash", "device_shard_proof", "biometric_token"},
     *             @OA\Property(
     *                 property="user_op_hash",
     *                 type="string",
     *                 description="32-byte hex hash of the UserOperation",
     *                 example="0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef"
     *             ),
     *             @OA\Property(
     *                 property="device_shard_proof",
     *                 type="string",
     *                 description="Signature from device's key shard",
     *                 example="0xabcdef..."
     *             ),
     *             @OA\Property(
     *                 property="biometric_token",
     *                 type="string",
     *                 description="Token from biometric authentication",
     *                 example="eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCJ9..."
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="UserOperation signed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="auth_shard_signature", type="string", description="Server's signature from auth shard"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time", description="When the signature expires"),
     *                 @OA\Property(property="signed_at", type="string", format="date-time", description="When the signature was created")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Signing failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="ERR_RELAYER_203"),
     *                 @OA\Property(property="message", type="string", example="Biometric verification failed")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function sign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_op_hash' => 'required|string|regex:/^0x[a-fA-F0-9]{64}$/',
            // ECDSA signatures are 65 bytes (130 hex chars) + 0x prefix = max 132 chars
            // Allow up to 260 hex chars for flexibility with different signature schemes
            'device_shard_proof' => 'required|string|regex:/^0x[a-fA-F0-9]{1,260}$/',
            'biometric_token'    => 'required|string|min:32|max:2048',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $result = $this->signingService->signUserOperation(
                user: $user,
                userOpHash: $validated['user_op_hash'],
                deviceShardProof: $validated['device_shard_proof'],
                biometricToken: $validated['biometric_token']
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'auth_shard_signature' => $result['auth_shard_signature'],
                    'expires_at'           => $result['expires_at']->format('c'),
                    'signed_at'            => $result['signed_at']->format('c'),
                ],
            ]);
        } catch (UserOpSigningException $e) {
            Log::warning('UserOp signing failed', [
                'user_id'    => $user->id,
                'error_code' => $e->getErrorCode(),
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }
}
