<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\X402;

use App\Domain\X402\Enums\X402Network;
use App\Domain\X402\Services\X402DiscoveryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * X402 Protocol Status Controller.
 *
 * Provides public endpoints for protocol status and supported networks/assets.
 */
#[OA\Tag(
    name: 'X402 Protocol',
    description: 'HTTP 402 Payment Protocol - native micropayments for APIs'
)]
class X402StatusController extends Controller
{
    /**
     * Get x402 protocol status.
     */
    #[OA\Get(
        path: '/api/v1/x402/status',
        summary: 'Get x402 protocol status and configuration',
        tags: ['X402 Protocol']
    )]
    #[OA\Response(
        response: 200,
        description: 'Protocol status',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'enabled', type: 'boolean'),
        new OA\Property(property: 'version', type: 'integer'),
        new OA\Property(property: 'protocol', type: 'string', example: 'x402'),
        new OA\Property(property: 'default_network', type: 'string', example: 'eip155:8453'),
        new OA\Property(property: 'supported_schemes', type: 'array', items: new OA\Items(type: 'string')),
        ])
    )]
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
     * Well-known x402 protocol discovery endpoint.
     */
    #[OA\Get(
        path: '/.well-known/x402-configuration',
        operationId: 'x402WellKnown',
        summary: 'x402 protocol discovery endpoint',
        tags: ['X402 Protocol']
    )]
    #[OA\Response(
        response: 200,
        description: 'x402 protocol discovery configuration',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'x402_version', type: 'integer', example: 2),
        new OA\Property(property: 'issuer', type: 'string'),
        new OA\Property(property: 'spec_url', type: 'string'),
        new OA\Property(property: 'default_network', type: 'string', example: 'eip155:8453'),
        new OA\Property(property: 'supported_networks', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'testnet', type: 'boolean'),
        ])),
        new OA\Property(property: 'supported_assets', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'supported_schemes', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'facilitator', type: 'object'),
        new OA\Property(property: 'endpoints', type: 'object'),
        new OA\Property(property: 'contracts', type: 'object'),
        ])
    )]
    public function wellKnown(X402DiscoveryService $discovery): JsonResponse
    {
        return response()->json($discovery->getWellKnownConfiguration());
    }

    /**
     * Get supported networks and assets.
     */
    #[OA\Get(
        path: '/api/v1/x402/supported',
        summary: 'List supported networks and payment assets',
        tags: ['X402 Protocol']
    )]
    #[OA\Response(
        response: 200,
        description: 'Supported networks and assets',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'networks', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'id', type: 'string', example: 'eip155:8453'),
        new OA\Property(property: 'name', type: 'string', example: 'Base Mainnet'),
        new OA\Property(property: 'testnet', type: 'boolean'),
        new OA\Property(property: 'usdc_address', type: 'string'),
        new OA\Property(property: 'usdc_decimals', type: 'integer', example: 6),
        ])),
        new OA\Property(property: 'contracts', type: 'object', properties: [
        new OA\Property(property: 'permit2', type: 'string'),
        new OA\Property(property: 'exact_permit2_proxy', type: 'string'),
        ]),
        ])
    )]
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
