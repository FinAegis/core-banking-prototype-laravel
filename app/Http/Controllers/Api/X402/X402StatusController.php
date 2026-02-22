<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\X402;

use App\Domain\X402\Enums\X402Network;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * X402 Protocol Status Controller.
 *
 * Provides public endpoints for protocol status and supported networks/assets.
 *
 * @OA\Tag(
 *     name="X402 Protocol",
 *     description="HTTP 402 Payment Protocol - native micropayments for APIs"
 * )
 */
class X402StatusController extends Controller
{
    /**
     * Get x402 protocol status.
     *
     * @OA\Get(
     *     path="/api/v1/x402/status",
     *     summary="Get x402 protocol status and configuration",
     *     tags={"X402 Protocol"},
     *     @OA\Response(
     *         response=200,
     *         description="Protocol status",
     *         @OA\JsonContent(
     *             @OA\Property(property="enabled", type="boolean"),
     *             @OA\Property(property="version", type="integer"),
     *             @OA\Property(property="protocol", type="string", example="x402"),
     *             @OA\Property(property="default_network", type="string", example="eip155:8453"),
     *             @OA\Property(property="supported_schemes", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'data' => [
                'enabled'           => (bool) config('x402.enabled', false),
                'version'           => (int) config('x402.version', 2),
                'protocol'          => 'x402',
                'default_network'   => config('x402.server.default_network', 'eip155:8453'),
                'supported_schemes' => ['exact'],
                'client_enabled'    => (bool) config('x402.client.enabled', false),
            ],
        ]);
    }

    /**
     * Get supported networks and assets.
     *
     * @OA\Get(
     *     path="/api/v1/x402/supported",
     *     summary="List supported networks and payment assets",
     *     tags={"X402 Protocol"},
     *     @OA\Response(
     *         response=200,
     *         description="Supported networks and assets",
     *         @OA\JsonContent(
     *             @OA\Property(property="networks", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="string", example="eip155:8453"),
     *                 @OA\Property(property="name", type="string", example="Base Mainnet"),
     *                 @OA\Property(property="testnet", type="boolean"),
     *                 @OA\Property(property="usdc_address", type="string"),
     *                 @OA\Property(property="usdc_decimals", type="integer", example=6)
     *             )),
     *             @OA\Property(property="contracts", type="object",
     *                 @OA\Property(property="permit2", type="string"),
     *                 @OA\Property(property="exact_permit2_proxy", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function supported(): JsonResponse
    {
        $networks = collect(X402Network::cases())->map(fn (X402Network $n) => [
            'id'            => $n->value,
            'name'          => $n->label(),
            'testnet'       => $n->isTestnet(),
            'chain_id'      => $n->chainId(),
            'usdc_address'  => $n->usdcAddress(),
            'usdc_decimals' => $n->usdcDecimals(),
            'explorer_url'  => $n->explorerUrl(),
        ]);

        return response()->json([
            'data' => [
                'networks'  => $networks->values(),
                'contracts' => [
                    'permit2'             => config('x402.contracts.permit2'),
                    'exact_permit2_proxy' => config('x402.contracts.exact_permit2_proxy'),
                ],
                'supported_schemes' => ['exact'],
                'supported_assets'  => ['USDC'],
            ],
        ]);
    }
}
