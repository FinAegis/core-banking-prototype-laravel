<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Relayer;

use App\Domain\Relayer\Exceptions\SmartAccountException;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Smart Account Controller.
 *
 * Handles ERC-4337 smart account creation and nonce management.
 *
 * @OA\Tag(
 *     name="Smart Accounts",
 *     description="ERC-4337 account abstraction management"
 * )
 */
class SmartAccountController extends Controller
{
    public function __construct(
        private readonly SmartAccountService $smartAccountService,
    ) {
    }

    /**
     * Get the user's primary smart account.
     *
     * Returns the first smart account created for the authenticated user,
     * optionally filtered by network. Useful for mobile apps that need
     * a single account reference.
     *
     * @OA\Get(
     *     path="/api/v1/relayer/account",
     *     summary="Get user's primary smart account",
     *     tags={"Smart Accounts"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="network",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"polygon", "base", "arbitrum"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Primary smart account",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="owner_address", type="string"),
     *                 @OA\Property(property="account_address", type="string"),
     *                 @OA\Property(property="network", type="string"),
     *                 @OA\Property(property="deployed", type="boolean"),
     *                 @OA\Property(property="nonce", type="integer"),
     *                 @OA\Property(property="pending_ops", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="No smart account found")
     * )
     */
    public function getAccount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $network = $request->query('network');

        $query = $this->smartAccountService->getUserAccounts($user);

        if ($network !== null && $network !== '') {
            $query = $query->where('network', $network);
        }

        $account = $query->sortBy('created_at')->first();

        if ($account === null) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => SmartAccountException::CODE_ACCOUNT_NOT_FOUND,
                    'message' => 'No smart account found for this user',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $account->toApiResponse(),
        ]);
    }

    /**
     * Create or retrieve a smart account.
     *
     * Returns the counterfactual smart account address for the given owner.
     * The account is computed deterministically but not deployed on-chain
     * until the first transaction.
     *
     * If owner_address is omitted, the server derives a deterministic address
     * from the authenticated user, enabling initial account creation during
     * onboarding when the user has no wallet yet.
     *
     * @OA\Post(
     *     path="/api/v1/relayer/account",
     *     summary="Create or retrieve a smart account",
     *     tags={"Smart Accounts"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"network"},
     *             @OA\Property(property="owner_address", type="string", nullable=true, example="0x742d35Cc6634C0532925a3b844Bc454e4438f44e", description="EOA owner address. If omitted, derived from authenticated user."),
     *             @OA\Property(property="network", type="string", enum={"polygon", "base", "arbitrum"}, example="polygon")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Smart account details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="owner_address", type="string"),
     *                 @OA\Property(property="account_address", type="string"),
     *                 @OA\Property(property="network", type="string"),
     *                 @OA\Property(property="deployed", type="boolean"),
     *                 @OA\Property(property="nonce", type="integer"),
     *                 @OA\Property(property="pending_ops", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'owner_address' => 'nullable|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'network'       => 'required|string|in:polygon,base,arbitrum',
        ]);

        try {
            /** @var User $user */
            $user = $request->user();

            // Derive owner address from user if not provided (onboarding flow)
            $ownerAddress = $validated['owner_address']
                ?? $this->deriveOwnerAddress($user);

            $account = $this->smartAccountService->getOrCreateAccount(
                $user,
                $ownerAddress,
                $validated['network']
            );

            return response()->json([
                'success' => true,
                'data'    => $account->toApiResponse(),
            ]);
        } catch (SmartAccountException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => $e->errorCode,
                    'message' => $e->getMessage(),
                ],
            ], $e->httpStatusCode);
        } catch (Throwable $e) {
            Log::error('Smart account creation failed', [
                'error'   => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'network' => $validated['network'],
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_RELAYER_100',
                    'message' => 'Failed to create smart account',
                ],
            ], 500);
        }
    }

    /**
     * Get nonce and pending operations for a smart account.
     *
     * @OA\Get(
     *     path="/api/v1/relayer/nonce/{address}",
     *     summary="Get nonce and pending ops for a smart account",
     *     tags={"Smart Accounts"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="address",
     *         in="path",
     *         required=true,
     *         description="Owner address (EOA)",
     *         @OA\Schema(type="string", example="0x742d35Cc6634C0532925a3b844Bc454e4438f44e")
     *     ),
     *     @OA\Parameter(
     *         name="network",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", enum={"polygon", "base", "arbitrum"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Nonce information",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="nonce", type="integer", example=5),
     *                 @OA\Property(property="pending_ops", type="integer", example=1),
     *                 @OA\Property(property="deployed", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Account not found")
     * )
     */
    public function getNonce(Request $request, string $address): JsonResponse
    {
        // Validate address format
        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => SmartAccountException::CODE_INVALID_NETWORK,
                    'message' => 'Invalid address format',
                ],
            ], 400);
        }

        $network = $request->query('network');
        if (empty($network) || ! in_array($network, ['polygon', 'base', 'arbitrum'])) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => SmartAccountException::CODE_INVALID_NETWORK,
                    'message' => 'Network parameter is required and must be polygon, base, or arbitrum',
                ],
            ], 400);
        }

        try {
            $nonceInfo = $this->smartAccountService->getNonceInfo($address, $network);

            return response()->json([
                'success' => true,
                'data'    => $nonceInfo,
            ]);
        } catch (SmartAccountException $e) {
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
     * Get init code for a smart account deployment.
     *
     * Returns the init code needed to deploy the smart account on first transaction.
     * Returns empty string if account is already deployed.
     *
     * @OA\Get(
     *     path="/api/v1/relayer/init-code/{address}",
     *     summary="Get init code for account deployment",
     *     tags={"Smart Accounts"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="address",
     *         in="path",
     *         required=true,
     *         description="Owner address (EOA)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="network",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", enum={"polygon", "base", "arbitrum"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Init code",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="init_code", type="string"),
     *                 @OA\Property(property="needs_deployment", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function getInitCode(Request $request, string $address): JsonResponse
    {
        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_ADDRESS',
                    'message' => 'Invalid address format',
                ],
            ], 400);
        }

        $network = $request->query('network');
        if (empty($network) || ! in_array($network, ['polygon', 'base', 'arbitrum'])) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => SmartAccountException::CODE_INVALID_NETWORK,
                    'message' => 'Network parameter is required',
                ],
            ], 400);
        }

        try {
            $needsDeployment = $this->smartAccountService->needsInitCode($address, $network);
            $initCode = $needsDeployment ? $this->smartAccountService->getInitCode($address, $network) : '';

            return response()->json([
                'success' => true,
                'data'    => [
                    'init_code'        => $initCode,
                    'needs_deployment' => $needsDeployment,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_RELAYER_100',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * List user's smart accounts.
     *
     * @OA\Get(
     *     path="/api/v1/relayer/accounts",
     *     summary="List user's smart accounts",
     *     tags={"Smart Accounts"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of smart accounts",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="owner_address", type="string"),
     *                 @OA\Property(property="account_address", type="string"),
     *                 @OA\Property(property="network", type="string"),
     *                 @OA\Property(property="deployed", type="boolean")
     *             ))
     *         )
     *     )
     * )
     */
    public function listAccounts(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $accounts = $this->smartAccountService->getUserAccounts($user);

        return response()->json([
            'success' => true,
            'data'    => $accounts->map(fn ($account) => $account->toApiResponse()),
        ]);
    }

    /**
     * Derive a deterministic EOA-like address from a user's identity.
     *
     * Used during onboarding when the user has no external wallet.
     * The address is computed as keccak256(user_id + app_key)[12:] to produce
     * a valid 20-byte Ethereum address deterministically.
     */
    private function deriveOwnerAddress(User $user): string
    {
        $seed = $user->id . ':' . config('app.key');
        $hash = hash('sha3-256', $seed);

        return '0x' . substr($hash, 24); // Last 20 bytes (40 hex chars)
    }
}
