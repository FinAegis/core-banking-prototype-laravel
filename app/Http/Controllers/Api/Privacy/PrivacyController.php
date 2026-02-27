<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Privacy;

use App\Domain\Privacy\Contracts\MerkleTreeServiceInterface;
use App\Domain\Privacy\Exceptions\CommitmentNotFoundException;
use App\Domain\Privacy\Models\DelegatedProofJob;
use App\Domain\Privacy\Services\DelegatedProofService;
use App\Domain\Privacy\Services\ProofOfInnocenceService;
use App\Domain\Privacy\Services\RailgunPrivacyService;
use App\Domain\Privacy\Services\SrsManifestService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Privacy API Controller.
 *
 * Handles privacy pool Merkle tree operations for mobile clients.
 *
 * @OA\Tag(
 *     name="Privacy",
 *     description="Privacy pool Merkle tree and proof endpoints"
 * )
 */
class PrivacyController extends Controller
{
    public function __construct(
        private readonly MerkleTreeServiceInterface $merkleService,
        private readonly SrsManifestService $srsManifestService,
        private readonly DelegatedProofService $delegatedProofService,
        private readonly ProofOfInnocenceService $proofOfInnocenceService,
        private readonly ?RailgunPrivacyService $railgunService = null,
    ) {
    }

    private function isRailgunMode(): bool
    {
        return $this->railgunService !== null
            && config('privacy.zk.provider') === 'railgun';
    }

    private function railgun(): RailgunPrivacyService
    {
        assert($this->railgunService !== null);

        return $this->railgunService;
    }

    /**
     * Get the current Merkle root for a network.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/merkle-root",
     *     operationId="getMerkleRoot",
     *     tags={"Privacy"},
     *     summary="Get the current Merkle root for a privacy pool",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="network",
     *         in="query",
     *         required=true,
     *         description="Blockchain network (polygon, base, arbitrum)",
     *         @OA\Schema(type="string", enum={"polygon", "base", "arbitrum"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Current Merkle root",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="root", type="string", example="0x1234..."),
     *                 @OA\Property(property="network", type="string", example="polygon"),
     *                 @OA\Property(property="leaf_count", type="integer", example=1000),
     *                 @OA\Property(property="tree_depth", type="integer", example=32),
     *                 @OA\Property(property="block_number", type="integer", example=55000000),
     *                 @OA\Property(property="synced_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid network"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getMerkleRoot(Request $request): JsonResponse
    {
        $network = $request->query('network');

        if (empty($network) || ! is_string($network)) {
            return response()->json([
                'error' => [
                    'code'    => 'ERR_PRIVACY_306',
                    'message' => 'Network parameter is required',
                ],
            ], 400);
        }

        try {
            $root = $this->merkleService->getMerkleRoot($network);

            return response()->json([
                'data' => $root->toArray(),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code'               => 'ERR_PRIVACY_307',
                    'message'            => $e->getMessage(),
                    'supported_networks' => $this->merkleService->getSupportedNetworks(),
                ],
            ], 400);
        }
    }

    /**
     * Get the Merkle proof path for a commitment.
     *
     * @OA\Post(
     *     path="/api/v1/privacy/merkle-path",
     *     operationId="getMerklePath",
     *     tags={"Privacy"},
     *     summary="Get Merkle proof path for a commitment",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"commitment", "network"},
     *             @OA\Property(property="commitment", type="string", description="32-byte hex commitment with 0x prefix"),
     *             @OA\Property(property="network", type="string", enum={"polygon", "base", "arbitrum"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merkle proof path",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="commitment", type="string"),
     *                 @OA\Property(property="root", type="string"),
     *                 @OA\Property(property="network", type="string"),
     *                 @OA\Property(property="leaf_index", type="integer"),
     *                 @OA\Property(property="siblings", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="path_indices", type="array", @OA\Items(type="integer")),
     *                 @OA\Property(property="proof_depth", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid parameters"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Commitment not found in tree")
     * )
     */
    public function getMerklePath(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commitment' => 'required|string|regex:/^(0x)?[a-fA-F0-9]{64}$/',
            'network'    => 'required|string',
        ]);

        try {
            $path = $this->merkleService->getMerklePath(
                $validated['commitment'],
                $validated['network']
            );

            return response()->json([
                'data' => $path->toArray(),
            ]);
        } catch (CommitmentNotFoundException $e) {
            return response()->json([
                'error' => [
                    'code'       => $e->getErrorCode(),
                    'message'    => $e->getMessage(),
                    'commitment' => $e->commitment,
                    'network'    => $e->network,
                ],
            ], $e->getHttpStatusCode());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code'               => 'ERR_PRIVACY_307',
                    'message'            => $e->getMessage(),
                    'supported_networks' => $this->merkleService->getSupportedNetworks(),
                ],
            ], 400);
        }
    }

    /**
     * Verify a commitment against a Merkle proof.
     *
     * @OA\Post(
     *     path="/api/v1/privacy/verify-commitment",
     *     operationId="verifyCommitment",
     *     tags={"Privacy"},
     *     summary="Verify a commitment exists in the tree",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"commitment", "network", "siblings", "path_indices"},
     *             @OA\Property(property="commitment", type="string"),
     *             @OA\Property(property="network", type="string"),
     *             @OA\Property(property="root", type="string"),
     *             @OA\Property(property="leaf_index", type="integer"),
     *             @OA\Property(property="siblings", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="path_indices", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Verification result",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="valid", type="boolean"),
     *                 @OA\Property(property="commitment", type="string"),
     *                 @OA\Property(property="network", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid parameters"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function verifyCommitment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commitment'     => 'required|string|regex:/^(0x)?[a-fA-F0-9]{64}$/',
            'network'        => 'required|string',
            'root'           => 'required|string|regex:/^(0x)?[a-fA-F0-9]{64}$/',
            'leaf_index'     => 'required|integer|min:0',
            'siblings'       => 'required|array',
            'siblings.*'     => 'required|string|regex:/^(0x)?[a-fA-F0-9]{64}$/',
            'path_indices'   => 'required|array',
            'path_indices.*' => 'required|integer|min:0|max:1',
        ]);

        try {
            $path = \App\Domain\Privacy\ValueObjects\MerklePath::fromArray([
                'commitment'   => $validated['commitment'],
                'root'         => $validated['root'],
                'network'      => $validated['network'],
                'leaf_index'   => $validated['leaf_index'],
                'siblings'     => $validated['siblings'],
                'path_indices' => $validated['path_indices'],
            ]);

            $isValid = $this->merkleService->verifyCommitment($validated['commitment'], $path);

            return response()->json([
                'data' => [
                    'valid'      => $isValid,
                    'commitment' => $validated['commitment'],
                    'network'    => $validated['network'],
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'ERR_PRIVACY_308',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get supported networks.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/networks",
     *     operationId="getPrivacyNetworks",
     *     tags={"Privacy"},
     *     summary="Get supported privacy pool networks",
     *     @OA\Response(
     *         response=200,
     *         description="Supported networks",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="networks", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="tree_depth", type="integer"),
     *                 @OA\Property(property="provider", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function getNetworks(): JsonResponse
    {
        return response()->json([
            'data' => [
                'networks'   => $this->merkleService->getSupportedNetworks(),
                'tree_depth' => $this->merkleService->getTreeDepth(),
                'provider'   => $this->merkleService->getProviderName(),
            ],
        ]);
    }

    /**
     * Trigger a Merkle tree sync.
     *
     * @OA\Post(
     *     path="/api/v1/privacy/sync",
     *     operationId="syncMerkleTree",
     *     tags={"Privacy"},
     *     summary="Trigger a Merkle tree sync from the blockchain",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"network"},
     *             @OA\Property(property="network", type="string", enum={"polygon", "base", "arbitrum"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sync completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="root", type="string"),
     *                 @OA\Property(property="network", type="string"),
     *                 @OA\Property(property="synced_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid network"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function syncTree(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'network' => 'required|string',
        ]);

        try {
            $root = $this->merkleService->syncTree($validated['network']);

            return response()->json([
                'data'    => $root->toArray(),
                'message' => 'Merkle tree synced successfully',
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code'               => 'ERR_PRIVACY_307',
                    'message'            => $e->getMessage(),
                    'supported_networks' => $this->merkleService->getSupportedNetworks(),
                ],
            ], 400);
        }
    }

    /**
     * Get the SRS manifest for mobile ZK proof generation.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/srs-manifest",
     *     operationId="getSrsManifest",
     *     tags={"Privacy"},
     *     summary="Get SRS (Structured Reference String) manifest for ZK circuits",
     *     description="Returns the manifest of SRS files required for mobile ZK proof generation. Mobile clients use this to download the required cryptographic parameters.",
     *     @OA\Response(
     *         response=200,
     *         description="SRS manifest",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="version", type="string", example="1.0.0"),
     *                 @OA\Property(property="cdn_base_url", type="string", example="https://cdn.finaegis.com/srs"),
     *                 @OA\Property(property="total_size", type="integer", example=47000000),
     *                 @OA\Property(property="required_size", type="integer", example=27000000),
     *                 @OA\Property(property="required_count", type="integer", example=2),
     *                 @OA\Property(property="total_count", type="integer", example=3),
     *                 @OA\Property(
     *                     property="circuits",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="name", type="string", example="shield_1_1"),
     *                         @OA\Property(property="version", type="string", example="1.0.0"),
     *                         @OA\Property(property="size", type="integer", example=15000000),
     *                         @OA\Property(property="size_human", type="string", example="14.31 MB"),
     *                         @OA\Property(property="required", type="boolean", example=true),
     *                         @OA\Property(property="download_url", type="string", example="https://cdn.finaegis.com/srs/1.0.0/shield_1_1.srs"),
     *                         @OA\Property(property="checksum", type="string", example="abc123..."),
     *                         @OA\Property(property="checksum_algorithm", type="string", example="sha256")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getSrsManifest(): JsonResponse
    {
        return response()->json([
            'data' => $this->srsManifestService->getManifest(),
        ]);
    }

    /**
     * Track SRS download for analytics.
     *
     * @OA\Post(
     *     path="/api/v1/privacy/srs-downloaded",
     *     operationId="trackSrsDownload",
     *     tags={"Privacy"},
     *     summary="Track SRS file download completion",
     *     description="Allows mobile clients to report successful SRS downloads for analytics and capability tracking.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"circuits"},
     *             @OA\Property(
     *                 property="circuits",
     *                 type="array",
     *                 description="Names of the circuits that were downloaded",
     *                 @OA\Items(type="string", example="shield_1_1")
     *             ),
     *             @OA\Property(
     *                 property="device_info",
     *                 type="string",
     *                 description="Optional device information for analytics",
     *                 example="iPhone 14 Pro / iOS 17.2"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Download tracked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="tracked", type="boolean", example=true),
     *                 @OA\Property(property="circuits", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="srs_version", type="string", example="1.0.0")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid circuits"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function trackSrsDownload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'circuits'    => 'required|array|min:1',
            'circuits.*'  => 'required|string|max:50',
            'device_info' => 'nullable|string|max:255',
        ]);

        // Validate that all circuits exist in the manifest
        $validCircuits = [];
        foreach ($validated['circuits'] as $circuitName) {
            $circuit = $this->srsManifestService->getCircuit($circuitName);
            if ($circuit === null) {
                return response()->json([
                    'error' => [
                        'code'               => 'ERR_PRIVACY_309',
                        'message'            => "Unknown circuit: {$circuitName}",
                        'available_circuits' => $this->srsManifestService->getCircuits()
                            ->map(fn ($c) => $c->name)
                            ->toArray(),
                    ],
                ], 400);
            }
            $validCircuits[] = $circuitName;
        }

        /** @var User $user */
        $user = $request->user();

        $this->srsManifestService->trackDownload(
            $user,
            $validCircuits,
            $validated['device_info'] ?? ''
        );

        return response()->json([
            'data' => [
                'tracked'     => true,
                'circuits'    => $validCircuits,
                'srs_version' => $this->srsManifestService->getVersion(),
            ],
        ]);
    }

    /**
     * Get privacy pool statistics.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/pool-stats",
     *     operationId="getPrivacyPoolStats",
     *     tags={"Privacy"},
     *     summary="Get privacy pool statistics",
     *     description="Returns aggregate statistics about the privacy pool including participant count and anonymity strength.",
     *     @OA\Response(
     *         response=200,
     *         description="Pool statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="totalPoolSize", type="number", example=67630),
     *                 @OA\Property(property="poolSizeCurrency", type="string", example="USD"),
     *                 @OA\Property(property="participantCount", type="integer", example=10),
     *                 @OA\Property(property="privacyStrength", type="string", example="weak"),
     *                 @OA\Property(property="lastUpdated", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function getPoolStats(): JsonResponse
    {
        $networks = $this->merkleService->getSupportedNetworks();
        $totalParticipants = 0;

        foreach ($networks as $network) {
            $root = $this->merkleService->getMerkleRoot($network);
            $totalParticipants += $root->leafCount;
        }

        // Demo values — in production these would aggregate on-chain TVL
        $totalPoolSize = $totalParticipants * 6763;

        $strength = match (true) {
            $totalParticipants >= 1000 => 'strong',
            $totalParticipants >= 100  => 'moderate',
            default                    => 'weak',
        };

        return response()->json([
            'data' => [
                'totalPoolSize'    => $totalPoolSize,
                'poolSizeCurrency' => 'USD',
                'participantCount' => $totalParticipants,
                'privacyStrength'  => $strength,
                'lastUpdated'      => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get shielded balances per token.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/balances",
     *     operationId="getShieldedBalances",
     *     tags={"Privacy"},
     *     summary="Get shielded balances per token",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Shielded balances",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="token", type="string", example="USDC"),
     *                 @OA\Property(property="balance", type="string", example="0.00"),
     *                 @OA\Property(property="network", type="string", example="polygon")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getShieldedBalances(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->isRailgunMode()) {
            $network = $request->query('network');
            $balances = $this->railgun()->getShieldedBalances(
                $user,
                is_string($network) ? $network : null,
            );

            return response()->json([
                'success' => true,
                'data'    => $balances,
            ]);
        }

        // Demo fallback
        $networks = $this->merkleService->getSupportedNetworks();
        $balances = [];

        foreach ($networks as $network) {
            foreach (['USDC', 'USDT', 'WETH'] as $token) {
                $balances[] = [
                    'token'   => $token,
                    'balance' => '0.00',
                    'network' => $network,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $balances,
        ]);
    }

    /**
     * Get total shielded balance aggregated across tokens.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/total-balance",
     *     operationId="getTotalShieldedBalance",
     *     tags={"Privacy"},
     *     summary="Get total shielded balance in USD",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Total shielded balance",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_balance", type="string", example="0.00"),
     *                 @OA\Property(property="currency", type="string", example="USD")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getTotalShieldedBalance(Request $request): JsonResponse
    {
        if ($this->isRailgunMode()) {
            /** @var User $user */
            $user = $request->user();
            $data = $this->railgun()->getTotalShieldedBalance($user);

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        }

        // Demo fallback
        return response()->json([
            'success' => true,
            'data'    => [
                'total_balance' => '0.00',
                'currency'      => 'USD',
            ],
        ]);
    }

    /**
     * Get privacy transactions for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/transactions",
     *     operationId="getPrivacyTransactions",
     *     tags={"Privacy"},
     *     summary="Get privacy transaction history",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Privacy transactions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getPrivacyTransactions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $limit = min((int) $request->query('limit', '20'), 100);

        $jobs = DelegatedProofJob::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $jobs->map(fn (DelegatedProofJob $job) => $job->toApiResponse()),
        ]);
    }

    /**
     * Shield tokens (create shielded notes).
     *
     * @OA\Post(
     *     path="/api/v1/privacy/shield",
     *     operationId="shieldTokens",
     *     tags={"Privacy"},
     *     summary="Shield tokens into the privacy pool",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "token", "network"},
     *             @OA\Property(property="amount", type="string", example="100.00"),
     *             @OA\Property(property="token", type="string", example="USDC"),
     *             @OA\Property(property="network", type="string", example="polygon"),
     *             @OA\Property(property="encrypted_inputs", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Shield operation initiated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function shield(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'           => 'required|string',
            'token'            => 'required|string',
            'network'          => 'required|string',
            'encrypted_inputs' => 'nullable|string',
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($this->isRailgunMode()) {
            $result = $this->railgun()->shield(
                $user,
                $validated['token'],
                $validated['amount'],
                $validated['network'],
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ], 201);
        }

        // Demo fallback: delegated proof
        $job = $this->delegatedProofService->requestProof(
            $user,
            'shield_1_1',
            $validated['network'],
            ['amount' => $validated['amount'], 'token' => $validated['token']],
            $validated['encrypted_inputs'] ?? '',
        );

        return response()->json([
            'success' => true,
            'data'    => $job->toApiResponse(),
        ], 201);
    }

    /**
     * Unshield tokens (withdraw from privacy pool).
     *
     * @OA\Post(
     *     path="/api/v1/privacy/unshield",
     *     operationId="unshieldTokens",
     *     tags={"Privacy"},
     *     summary="Unshield tokens from the privacy pool",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "token", "network", "recipient"},
     *             @OA\Property(property="amount", type="string", example="50.00"),
     *             @OA\Property(property="token", type="string", example="USDC"),
     *             @OA\Property(property="network", type="string", example="polygon"),
     *             @OA\Property(property="recipient", type="string", example="0x1234..."),
     *             @OA\Property(property="encrypted_inputs", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Unshield operation initiated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function unshield(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'           => 'required|string',
            'token'            => 'required|string',
            'network'          => 'required|string',
            'recipient'        => 'required|string',
            'encrypted_inputs' => 'nullable|string',
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($this->isRailgunMode()) {
            $result = $this->railgun()->unshield(
                $user,
                $validated['recipient'],
                $validated['token'],
                $validated['amount'],
                $validated['network'],
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ], 201);
        }

        // Demo fallback: delegated proof
        $job = $this->delegatedProofService->requestProof(
            $user,
            'unshield_2_1',
            $validated['network'],
            [
                'amount'    => $validated['amount'],
                'token'     => $validated['token'],
                'recipient' => $validated['recipient'],
            ],
            $validated['encrypted_inputs'] ?? '',
        );

        return response()->json([
            'success' => true,
            'data'    => $job->toApiResponse(),
        ], 201);
    }

    /**
     * Private transfer within the privacy pool.
     *
     * @OA\Post(
     *     path="/api/v1/privacy/transfer",
     *     operationId="privateTransfer",
     *     tags={"Privacy"},
     *     summary="Transfer tokens privately within the pool",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "token", "network"},
     *             @OA\Property(property="amount", type="string", example="25.00"),
     *             @OA\Property(property="token", type="string", example="USDC"),
     *             @OA\Property(property="network", type="string", example="polygon"),
     *             @OA\Property(property="recipient_commitment", type="string"),
     *             @OA\Property(property="encrypted_inputs", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Private transfer initiated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function privateTransfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'               => 'required|string',
            'token'                => 'required|string',
            'network'              => 'required|string',
            'recipient_commitment' => 'nullable|string',
            'encrypted_inputs'     => 'nullable|string',
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($this->isRailgunMode()) {
            $result = $this->railgun()->privateTransfer(
                $user,
                $validated['recipient_commitment'] ?? '',
                $validated['token'],
                $validated['amount'],
                $validated['network'],
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ], 201);
        }

        // Demo fallback: delegated proof
        $job = $this->delegatedProofService->requestProof(
            $user,
            'transfer_2_2',
            $validated['network'],
            [
                'amount'               => $validated['amount'],
                'token'                => $validated['token'],
                'recipient_commitment' => $validated['recipient_commitment'] ?? '',
            ],
            $validated['encrypted_inputs'] ?? '',
        );

        return response()->json([
            'success' => true,
            'data'    => $job->toApiResponse(),
        ], 201);
    }

    /**
     * Get the viewing key for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/viewing-key",
     *     operationId="getViewingKey",
     *     tags={"Privacy"},
     *     summary="Get the user's viewing key for decrypting shielded notes",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Viewing key",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="viewing_key", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getViewingKey(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $viewingKey = $this->isRailgunMode()
            ? $this->railgun()->getViewingKey($user)
            : '0x' . hash('sha256', 'viewing_key_' . $user->id);

        return response()->json([
            'success' => true,
            'data'    => [
                'viewing_key' => $viewingKey,
                'created_at'  => $user->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Generate a proof of innocence.
     *
     * @OA\Post(
     *     path="/api/v1/privacy/proof-of-innocence",
     *     operationId="generateProofOfInnocence",
     *     tags={"Privacy"},
     *     summary="Generate a proof that funds are not from sanctioned sources",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="transaction_hashes", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="sanctions_list_root", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Proof generated"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function generateProofOfInnocence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_hashes'   => 'nullable|array',
            'transaction_hashes.*' => 'string',
            'sanctions_list_root'  => 'nullable|string',
        ]);

        /** @var User $user */
        $user = $request->user();

        $proof = $this->proofOfInnocenceService->generateSanctionsClearanceProof(
            (string) $user->id,
            $validated['transaction_hashes'] ?? [],
            $validated['sanctions_list_root'] ?? '0x' . str_repeat('0', 64),
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'proof_id'   => $proof->getProofHash(),
                'proof_type' => 'sanctions_clearance',
                'status'     => 'completed',
                'valid'      => true,
                'expires_at' => $proof->expiresAt->format('c'),
                'created_at' => now()->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Verify a proof of innocence.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/proof-of-innocence/{proofId}/verify",
     *     operationId="verifyProofOfInnocence",
     *     tags={"Privacy"},
     *     summary="Verify a proof of innocence",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="proofId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Verification result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="proof_id", type="string"),
     *                 @OA\Property(property="valid", type="boolean"),
     *                 @OA\Property(property="reason", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function verifyProofOfInnocence(Request $request, string $proofId): JsonResponse
    {
        // Demo implementation — in production would look up proof from store
        return response()->json([
            'success' => true,
            'data'    => [
                'proof_id'    => $proofId,
                'valid'       => true,
                'reason'      => null,
                'verified_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get the SRS download URL for a specific chain.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/srs-url",
     *     operationId="getSrsUrl",
     *     tags={"Privacy"},
     *     summary="Get the SRS download URL for a chain",
     *     @OA\Parameter(name="chain_id", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="SRS URL",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="version", type="string"),
     *                 @OA\Property(property="circuits", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function getSrsUrl(Request $request): JsonResponse
    {
        $chainId = $request->query('chain_id');
        $baseUrl = $this->srsManifestService->getCdnBaseUrl();
        $version = $this->srsManifestService->getVersion();

        $circuits = $this->srsManifestService->getCircuits()->map(fn ($circuit) => [
            'name'         => $circuit->name,
            'download_url' => $baseUrl . '/' . $version . '/' . $circuit->name . '.srs',
            'size'         => $circuit->size,
            'required'     => $circuit->required,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'url'      => $baseUrl,
                'version'  => $version,
                'chain_id' => $chainId,
                'circuits' => $circuits->toArray(),
            ],
        ]);
    }

    /**
     * Get the SRS download status for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/v1/privacy/srs-status",
     *     operationId="getSrsStatus",
     *     tags={"Privacy"},
     *     summary="Get SRS download status for the current user",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="SRS status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="has_required", type="boolean"),
     *                 @OA\Property(property="version", type="string"),
     *                 @OA\Property(property="required_circuits", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getSrsStatus(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $hasRequired = $this->srsManifestService->hasRequiredSrs($user);
        $requiredCircuits = $this->srsManifestService->getRequiredCircuits()
            ->map(fn ($circuit) => $circuit->name)
            ->toArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'has_required'      => $hasRequired,
                'version'           => $this->srsManifestService->getVersion(),
                'required_circuits' => $requiredCircuits,
            ],
        ]);
    }
}
