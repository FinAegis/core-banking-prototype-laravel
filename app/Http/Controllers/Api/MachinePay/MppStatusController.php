<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\MachinePay;

use App\Domain\MachinePay\Services\MppDiscoveryService;
use App\Domain\MachinePay\Services\MppRailResolverService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Machine Payments')]
class MppStatusController extends Controller
{
    public function __construct(
        private readonly MppRailResolverService $railResolver,
        private readonly MppDiscoveryService $discovery,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/mpp/status',
        operationId: 'mppStatus',
        summary: 'MPP protocol status',
        description: 'Returns whether MPP is enabled, protocol version, available payment rails, and MCP binding status.',
        tags: ['Machine Payments'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Protocol status',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'enabled', type: 'boolean'),
                    new OA\Property(property: 'version', type: 'integer'),
                    new OA\Property(property: 'available_rails', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'rail_count', type: 'integer'),
                    new OA\Property(property: 'mcp_enabled', type: 'boolean'),
                    new OA\Property(property: 'spec_url', type: 'string'),
                ]),
            ],
        ),
    )]
    public function status(): JsonResponse
    {
        $availableRails = $this->railResolver->getAvailableRailIds();

        return response()->json([
            'success' => true,
            'data'    => [
                'enabled'         => (bool) config('machinepay.enabled', false),
                'version'         => (int) config('machinepay.version', 1),
                'available_rails' => $availableRails,
                'rail_count'      => count($availableRails),
                'mcp_enabled'     => (bool) config('machinepay.mcp.enabled', true),
                'spec_url'        => 'https://paymentauth.org',
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/mpp/supported-rails',
        operationId: 'mppSupportedRails',
        summary: 'List supported payment rails',
        description: 'Returns detailed info for each payment rail: Stripe SPT, Tempo, Lightning, Card, and x402 USDC.',
        tags: ['Machine Payments'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Rail details',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'label', type: 'string'),
                        new OA\Property(property: 'description', type: 'string'),
                        new OA\Property(property: 'supports_fiat', type: 'boolean'),
                        new OA\Property(property: 'supports_crypto', type: 'boolean'),
                        new OA\Property(property: 'currencies', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'available', type: 'boolean'),
                    ],
                    type: 'object',
                )),
            ],
        ),
    )]
    public function supportedRails(): JsonResponse
    {
        $rails = $this->railResolver->getAvailableRails();

        $data = [];
        foreach ($rails as $id => $rail) {
            $railEnum = $rail->getRailIdentifier();
            $data[] = [
                'id'              => $id,
                'label'           => $railEnum->label(),
                'description'     => $railEnum->description(),
                'supports_fiat'   => $railEnum->supportsFiat(),
                'supports_crypto' => $railEnum->supportsCrypto(),
                'currencies'      => $railEnum->defaultCurrencies(),
                'available'       => $rail->isAvailable(),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    #[OA\Get(
        path: '/.well-known/mpp-configuration',
        operationId: 'mppWellKnown',
        summary: 'MPP discovery endpoint',
        description: 'Returns the .well-known/mpp-configuration JSON for protocol discovery.',
        tags: ['Machine Payments'],
    )]
    #[OA\Response(response: 200, description: 'MPP configuration JSON')]
    public function wellKnown(): JsonResponse
    {
        return response()->json($this->discovery->getWellKnownConfiguration());
    }
}
