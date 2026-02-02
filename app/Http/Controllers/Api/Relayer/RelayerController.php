<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Relayer;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\GasStationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @OA\Tag(
 *     name="Gas Relayer",
 *     description="Meta-transaction relayer for gasless stablecoin transfers"
 * )
 */
class RelayerController extends Controller
{
    public function __construct(
        private readonly GasStationService $gasStationService,
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
     * @OA\Post(
     *     path="/api/v1/relayer/sponsor",
     *     summary="Submit a sponsored transaction",
     *     tags={"Gas Relayer"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_address", "call_data", "signature"},
     *             @OA\Property(property="user_address", type="string", example="0x742d35Cc6634C0532925a3b844Bc454e4438f44e"),
     *             @OA\Property(property="call_data", type="string", description="Encoded transaction calldata"),
     *             @OA\Property(property="signature", type="string", description="User's signature"),
     *             @OA\Property(property="network", type="string", enum={"polygon", "arbitrum", "optimism", "base", "ethereum"}, example="polygon"),
     *             @OA\Property(property="fee_token", type="string", enum={"USDC", "USDT"}, example="USDC"),
     *             @OA\Property(property="init_code", type="string", nullable=true, description="Factory calldata for first-time account deployment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction sponsored",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="tx_hash", type="string"),
     *                 @OA\Property(property="user_op_hash", type="string"),
     *                 @OA\Property(property="gas_used", type="integer"),
     *                 @OA\Property(property="fee_charged", type="string", example="0.050000"),
     *                 @OA\Property(property="fee_currency", type="string", example="USDC"),
     *                 @OA\Property(property="is_deployment", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Transaction failed"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function sponsor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
            'call_data'    => 'required|string|regex:/^0x[a-fA-F0-9]*$/',
            'signature'    => 'required|string|regex:/^0x[a-fA-F0-9]*$/',
            'network'      => 'nullable|string|in:polygon,arbitrum,optimism,base,ethereum',
            'fee_token'    => 'nullable|string|in:USDC,USDT',
            'init_code'    => 'nullable|string|regex:/^0x[a-fA-F0-9]*$/',
        ]);

        try {
            $network = SupportedNetwork::from($validated['network'] ?? 'polygon');
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
                'error'         => $e->getMessage(),
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
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Estimate gas fee for a transaction.
     *
     * @OA\Post(
     *     path="/api/v1/relayer/estimate",
     *     summary="Estimate gas fee in stablecoins",
     *     tags={"Gas Relayer"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"call_data"},
     *             @OA\Property(property="call_data", type="string"),
     *             @OA\Property(property="network", type="string", enum={"polygon", "arbitrum", "optimism", "base", "ethereum"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Fee estimate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="estimated_gas", type="integer"),
     *                 @OA\Property(property="fee_usdc", type="string", example="0.050000"),
     *                 @OA\Property(property="fee_usdt", type="string", example="0.050000"),
     *                 @OA\Property(property="network", type="string")
     *             )
     *         )
     *     )
     * )
     */
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
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_RELAYER_002',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get supported networks and their detailed configuration.
     *
     * Returns ERC-4337 infrastructure addresses and current gas prices for each network.
     *
     * @OA\Get(
     *     path="/api/v1/relayer/networks",
     *     summary="List supported networks with ERC-4337 configuration",
     *     tags={"Gas Relayer"},
     *     @OA\Response(
     *         response=200,
     *         description="List of supported networks with detailed configuration",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="chain_id", type="integer", example=137),
     *                 @OA\Property(property="name", type="string", example="polygon"),
     *                 @OA\Property(property="entrypoint_address", type="string", example="0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789"),
     *                 @OA\Property(property="factory_address", type="string", example="0x..."),
     *                 @OA\Property(property="paymaster_address", type="string", example="0x..."),
     *                 @OA\Property(property="current_gas_price", type="string", example="30"),
     *                 @OA\Property(property="average_fee_usdc", type="string", example="0.0200"),
     *                 @OA\Property(property="congestion_level", type="string", enum={"low", "medium", "high"}, example="low"),
     *                 @OA\Property(property="fee_token", type="string", example="USDC")
     *             ))
     *         )
     *     )
     * )
     */
    public function networks(): JsonResponse
    {
        $networks = $this->gasStationService->getSupportedNetworks();

        return response()->json([
            'success' => true,
            'data'    => $networks,
        ]);
    }
}
