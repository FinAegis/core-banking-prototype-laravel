<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Messaging\A2AProtocolNegotiationService;
use App\Domain\AgentProtocol\Services\DIDService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Agent Protocol - Negotiation',
    description: 'Protocol version negotiation and agreement management endpoints'
)]
class AgentProtocolNegotiationController extends Controller
{
    public function __construct(
        private readonly A2AProtocolNegotiationService $negotiationService,
        private readonly DIDService $didService
    ) {
    }

        #[OA\Get(
            path: '/api/agent-protocol/protocol/versions',
            operationId: 'getProtocolVersions',
            tags: ['Agent Protocol - Negotiation'],
            summary: 'Get supported protocol versions'
        )]
    #[OA\Response(
        response: 200,
        description: 'List of supported protocol versions',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'versions', type: 'array', example: ['1.1', '1.0'], items: new OA\Items(type: 'string')),
        new OA\Property(property: 'preferred', type: 'string', example: '1.1'),
        ]),
        ])
    )]
    public function listVersions(): JsonResponse
    {
        $versions = $this->negotiationService->getSupportedVersions();

        return response()->json([
            'success' => true,
            'data'    => [
                'versions'  => $versions,
                'preferred' => $versions[0] ?? null,
            ],
        ]);
    }

        #[OA\Get(
            path: '/api/agent-protocol/protocol/versions/{version}/capabilities',
            operationId: 'getVersionCapabilities',
            tags: ['Agent Protocol - Negotiation'],
            summary: 'Get capabilities for a specific protocol version',
            parameters: [
            new OA\Parameter(name: 'version', in: 'path', required: true, description: 'Protocol version', schema: new OA\Schema(type: 'string', example: '1.1')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Version capabilities',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'version', type: 'string', example: '1.1'),
        new OA\Property(property: 'capabilities', type: 'object'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Version not supported'
    )]
    public function getVersionCapabilities(string $version): JsonResponse
    {
        $capabilities = $this->negotiationService->getVersionCapabilities($version);

        if ($capabilities === null) {
            return response()->json([
                'success' => false,
                'error'   => 'Protocol version not supported',
                'data'    => [
                    'requested' => $version,
                    'supported' => $this->negotiationService->getSupportedVersions(),
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'version'      => $version,
                'capabilities' => $capabilities,
            ],
        ]);
    }

        #[OA\Post(
            path: '/api/agent-protocol/agents/{did}/protocol/negotiate',
            operationId: 'initiateProtocolNegotiation',
            tags: ['Agent Protocol - Negotiation'],
            summary: 'Initiate protocol negotiation with another agent',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Initiator agent DID', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['target_did'], properties: [
            new OA\Property(property: 'target_did', type: 'string', description: 'Target agent DID'),
            new OA\Property(property: 'preferred_capabilities', type: 'array', description: 'Preferred capabilities for the negotiation', example: ['messaging', 'payments'], items: new OA\Items(type: 'string')),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Negotiation result',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'negotiation', type: 'object'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request'
    )]
    #[OA\Response(
        response: 500,
        description: 'Negotiation failed'
    )]
    public function negotiate(Request $request, string $did): JsonResponse
    {
        $validated = $request->validate([
            'target_did'               => 'required|string',
            'preferred_capabilities'   => 'nullable|array',
            'preferred_capabilities.*' => 'string',
        ]);

        // Validate initiator DID
        if (! $this->didService->validateDID($did)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid initiator DID format',
            ], 400);
        }

        // Validate target DID
        if (! $this->didService->validateDID($validated['target_did'])) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid target DID format',
            ], 400);
        }

        try {
            $result = $this->negotiationService->initiateNegotiation(
                $did,
                $validated['target_did'],
                $validated['preferred_capabilities'] ?? null
            );

            return response()->json([
                'success' => $result->isSuccess(),
                'data'    => [
                    'negotiation' => $result->toArray(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Protocol negotiation failed', [
                'initiator_did' => $did,
                'target_did'    => $validated['target_did'],
                'error'         => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Negotiation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{did}/protocol/agreements/{otherDid}',
            operationId: 'getProtocolAgreement',
            tags: ['Agent Protocol - Negotiation'],
            summary: 'Get protocol agreement between two agents',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'otherDid', in: 'path', required: true, description: 'Other agent DID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Protocol agreement details',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'agreement', type: 'object'),
        new OA\Property(property: 'has_agreement', type: 'boolean'),
        new OA\Property(property: 'is_valid', type: 'boolean'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid DID format'
    )]
    public function getAgreement(Request $request, string $did, string $otherDid): JsonResponse
    {
        // Validate DIDs
        if (! $this->didService->validateDID($did)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid agent DID format',
            ], 400);
        }

        if (! $this->didService->validateDID($otherDid)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid other agent DID format',
            ], 400);
        }

        $agreement = $this->negotiationService->getAgreedProtocol($did, $otherDid);
        $hasValidAgreement = $this->negotiationService->hasValidAgreement($did, $otherDid);

        return response()->json([
            'success' => true,
            'data'    => [
                'agreement'     => $agreement?->toArray(),
                'has_agreement' => $agreement !== null,
                'is_valid'      => $hasValidAgreement,
            ],
        ]);
    }

        #[OA\Delete(
            path: '/api/agent-protocol/agents/{did}/protocol/agreements/{otherDid}',
            operationId: 'revokeProtocolAgreement',
            tags: ['Agent Protocol - Negotiation'],
            summary: 'Revoke protocol agreement between two agents',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'otherDid', in: 'path', required: true, description: 'Other agent DID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Agreement revoked successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Protocol agreement revoked'),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid DID format'
    )]
    public function revokeAgreement(Request $request, string $did, string $otherDid): JsonResponse
    {
        // Validate DIDs
        if (! $this->didService->validateDID($did)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid agent DID format',
            ], 400);
        }

        if (! $this->didService->validateDID($otherDid)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid other agent DID format',
            ], 400);
        }

        $this->negotiationService->revokeAgreement($did, $otherDid);

        return response()->json([
            'success' => true,
            'message' => 'Protocol agreement revoked',
        ]);
    }

        #[OA\Post(
            path: '/api/agent-protocol/agents/{did}/protocol/agreements/{otherDid}/refresh',
            operationId: 'refreshProtocolAgreement',
            tags: ['Agent Protocol - Negotiation'],
            summary: 'Refresh/extend protocol agreement between two agents',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'otherDid', in: 'path', required: true, description: 'Other agent DID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Agreement refresh result',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'negotiation', type: 'object'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid DID format'
    )]
    #[OA\Response(
        response: 500,
        description: 'Refresh failed'
    )]
    public function refreshAgreement(Request $request, string $did, string $otherDid): JsonResponse
    {
        // Validate DIDs
        if (! $this->didService->validateDID($did)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid agent DID format',
            ], 400);
        }

        if (! $this->didService->validateDID($otherDid)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid other agent DID format',
            ], 400);
        }

        try {
            $result = $this->negotiationService->refreshAgreement($did, $otherDid);

            return response()->json([
                'success' => $result->isSuccess(),
                'data'    => [
                    'negotiation' => $result->toArray(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Protocol agreement refresh failed', [
                'agent_did' => $did,
                'other_did' => $otherDid,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Agreement refresh failed: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{did}/protocol/agreements/{otherDid}/check',
            operationId: 'checkProtocolAgreement',
            tags: ['Agent Protocol - Negotiation'],
            summary: 'Check if a valid protocol agreement exists',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'otherDid', in: 'path', required: true, description: 'Other agent DID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Agreement check result',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'has_valid_agreement', type: 'boolean'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid DID format'
    )]
    public function checkAgreement(Request $request, string $did, string $otherDid): JsonResponse
    {
        // Validate DIDs
        if (! $this->didService->validateDID($did)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid agent DID format',
            ], 400);
        }

        if (! $this->didService->validateDID($otherDid)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid other agent DID format',
            ], 400);
        }

        $hasValidAgreement = $this->negotiationService->hasValidAgreement($did, $otherDid);

        return response()->json([
            'success' => true,
            'data'    => [
                'has_valid_agreement' => $hasValidAgreement,
            ],
        ]);
    }
}
