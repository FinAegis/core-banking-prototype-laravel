<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Relayer;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\SponsorshipRequestRejectedException;
use App\Domain\Relayer\Services\GasStationService;
use App\Domain\Relayer\Services\SponsorshipRequestValidator;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecretRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\Tag(
    name: 'Gas Relayer',
    description: 'Meta-transaction relayer for gasless stablecoin transfers'
)]
class RelayerController extends Controller
{
    public function __construct(
        private readonly GasStationService $gasStationService,
        private readonly SponsorshipRequestValidator $sponsorshipValidator,
    ) {
    }

    /**
     * Sponsor a transaction (meta-transaction).
     *
     * Allows users to execute blockchain transactions without holding native
     * gas tokens (ETH/MATIC). The fee is deducted from their stablecoin balance.
     *
     * For first-time account deployment, include the init_code parameter with
     * the Smart Account factory call data.
     *
     * Available only when RELAYER_GENERIC_SPONSOR_ENABLED is on (404
     * otherwise). The sender must be a smart account / wallet address
     * registered to the caller, the network must be enabled for wallet sends
     * and the calldata must be an execute(USDC|USDT, 0, transfer(to, amount))
     * call (see SponsorshipRequestValidator). Each accepted request counts
     * against the daily sponsorship budget shared with wallet sends (429
     * once it is used up).
     */
    #[OA\Post(
        path: '/api/v1/relayer/sponsor',
        summary: 'Submit a sponsored transaction',
        tags: ['Gas Relayer'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['user_address', 'call_data', 'signature'], properties: [
        new OA\Property(property: 'user_address', type: 'string', example: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'),
        new OA\Property(property: 'call_data', type: 'string', description: 'Encoded transaction calldata'),
        new OA\Property(property: 'signature', type: 'string', description: 'User\'s signature'),
        new OA\Property(property: 'network', type: 'string', enum: ['polygon', 'arbitrum', 'optimism', 'base', 'ethereum'], example: 'polygon'),
        new OA\Property(property: 'fee_token', type: 'string', enum: ['USDC', 'USDT'], example: 'USDC'),
        new OA\Property(property: 'init_code', type: 'string', nullable: true, description: 'Factory calldata for first-time account deployment'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Transaction sponsored',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'tx_hash', type: 'string'),
        new OA\Property(property: 'user_op_hash', type: 'string'),
        new OA\Property(property: 'gas_used', type: 'integer'),
        new OA\Property(property: 'fee_charged', type: 'string', example: '0.050000'),
        new OA\Property(property: 'fee_currency', type: 'string', example: 'USDC'),
        new OA\Property(property: 'is_deployment', type: 'boolean', example: false),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Transaction failed'
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 403,
        description: 'Sender is not registered to the caller (ERR_RELAYER_301)'
    )]
    #[OA\Response(
        response: 404,
        description: 'Generic sponsorship is disabled (RELAYER_GENERIC_SPONSOR_ENABLED)'
    )]
    #[OA\Response(
        response: 422,
        description: 'Calldata, network or init code not allowed (ERR_RELAYER_302/303/304)'
    )]
    #[OA\Response(
        response: 429,
        description: 'Daily sponsorship limit reached (ERR_RELAYER_305 per user, ERR_RELAYER_306 platform-wide); shared with wallet sends'
    )]
    public function sponsor(Request $request): JsonResponse
    {
        // Max lengths: call_data 50KB (100000 hex chars), signature 132 chars (65 bytes), init_code 20KB
        $validated = $request->validate([
            'user_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'call_data'    => ['required', 'string', 'regex:/^0x[a-fA-F0-9]*$/', 'max:100002'],
            'signature'    => ['required', 'string', 'regex:/^0x[a-fA-F0-9]+$/', 'min:4', 'max:1000'],
            'network'      => 'nullable|string|in:polygon,arbitrum,optimism,base,ethereum',
            'fee_token'    => 'nullable|string|in:USDC,USDT',
            'init_code'    => ['nullable', 'string', 'regex:/^0x[a-fA-F0-9]*$/', 'max:40002'],
        ]);

        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Not authenticated.']], 401);
        }

        try {
            $network = $this->sponsorshipValidator->admit(
                user: $user,
                sender: $validated['user_address'],
                callData: $validated['call_data'],
                networkKey: $validated['network'] ?? 'polygon',
                initCode: $validated['init_code'] ?? null,
            );
        } catch (SponsorshipRequestRejectedException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => $e->errorCode,
                    'message' => $e->getMessage(),
                ],
            ], $e->httpStatusCode);
        }

        try {
            $feeToken = $validated['fee_token'] ?? 'USDC';
            $initCode = $validated['init_code'] ?? null;

            $result = $this->gasStationService->sponsorTransaction(
                userAddress: $validated['user_address'],
                callData: $validated['call_data'],
                signature: $validated['signature'],
                network: $network,
                feeToken: $feeToken,
                initCode: $initCode,
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Transaction sponsorship failed', [
                'error'         => SecretRedactor::message($e),
                'user_address'  => $validated['user_address'],
                'has_init_code' => isset($validated['init_code']),
            ]);

            $errorCode = str_contains($e->getMessage(), 'initCode')
                ? 'ERR_RELAYER_104'  // Invalid initCode
                : 'ERR_RELAYER_001'; // General error

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => $errorCode,
                    'message' => 'Transaction sponsorship failed. Please try again or contact support.',
                ],
            ], 400);
        }
    }

    /**
     * Estimate gas fee for a transaction.
     */
    #[OA\Post(
        path: '/api/v1/relayer/estimate',
        summary: 'Estimate gas fee in stablecoins',
        tags: ['Gas Relayer'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['call_data'], properties: [
        new OA\Property(property: 'call_data', type: 'string'),
        new OA\Property(property: 'network', type: 'string', enum: ['polygon', 'arbitrum', 'optimism', 'base', 'ethereum']),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Fee estimate',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'estimated_gas', type: 'integer'),
        new OA\Property(property: 'fee_usdc', type: 'string', example: '0.050000'),
        new OA\Property(property: 'fee_usdt', type: 'string', example: '0.050000'),
        new OA\Property(property: 'network', type: 'string'),
        ]),
        ])
    )]
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'call_data' => 'required|string|regex:/^0x[a-fA-F0-9]*$/',
            'network'   => 'nullable|string|in:polygon,arbitrum,optimism,base,ethereum',
        ]);

        try {
            $network = SupportedNetwork::from($validated['network'] ?? 'polygon');

            $estimate = $this->gasStationService->estimateFee(
                callData: $validated['call_data'],
                network: $network,
            );

            return response()->json([
                'success' => true,
                'data'    => $estimate,
            ]);
        } catch (Throwable $e) {
            Log::error('Gas estimation failed', [
                'error' => SecretRedactor::message($e),
            ]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_RELAYER_002',
                    'message' => 'Gas estimation failed. Please try again later.',
                ],
            ], 400);
        }
    }

    /**
     * Get supported networks and their detailed configuration.
     *
     * Returns ERC-4337 infrastructure addresses and current gas prices for each network.
     */
    #[OA\Get(
        path: '/api/v1/relayer/networks',
        summary: 'List supported networks with ERC-4337 configuration',
        tags: ['Gas Relayer']
    )]
    #[OA\Response(
        response: 200,
        description: 'List of supported networks with detailed configuration',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'chain_id', type: 'integer', example: 137),
        new OA\Property(property: 'name', type: 'string', example: 'polygon'),
        new OA\Property(property: 'entrypoint_address', type: 'string', example: '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789'),
        new OA\Property(property: 'factory_address', type: 'string', example: '0x...'),
        new OA\Property(property: 'paymaster_address', type: 'string', example: '0x...'),
        new OA\Property(property: 'current_gas_price', type: 'string', example: '30'),
        new OA\Property(property: 'average_fee_usdc', type: 'string', example: '0.0200'),
        new OA\Property(property: 'congestion_level', type: 'string', enum: ['low', 'medium', 'high'], example: 'low'),
        new OA\Property(property: 'fee_token', type: 'string', example: 'USDC'),
        ])),
        ])
    )]
    public function networks(): JsonResponse
    {
        $networks = $this->gasStationService->getSupportedNetworks();

        return response()->json([
            'success' => true,
            'data'    => $networks,
        ]);
    }
}
