<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Services\DIDService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Agent Protocol - Identity',
    description: 'Agent registration, discovery, and identity management endpoints'
)]
class AgentIdentityController extends Controller
{
    public function __construct(
        private readonly DIDService $didService,
        private readonly AgentRegistryService $agentRegistryService
    ) {
    }

        #[OA\Post(
            path: '/api/agent-protocol/agents/register',
            operationId: 'registerAgent',
            tags: ['Agent Protocol - Identity'],
            summary: 'Register a new agent in the system',
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'type'], properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Trading Agent Alpha'),
            new OA\Property(property: 'type', type: 'string', enum: ['service', 'user', 'system'], example: 'service'),
            new OA\Property(property: 'description', type: 'string', example: 'Automated trading agent'),
            new OA\Property(property: 'capabilities', type: 'array', example: ['payment', 'trading'], items: new OA\Items(type: 'string')),
            new OA\Property(property: 'metadata', type: 'object', example: ['version' => '1.0', 'provider' => 'FinAegis']),
            ]))
        )]
    #[OA\Response(
        response: 201,
        description: 'Agent registered successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'agent_id', type: 'string'),
        new OA\Property(property: 'did', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'registered_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => ['required', 'string', Rule::in(['service', 'user', 'system'])],
            'description'    => 'nullable|string|max:1000',
            'capabilities'   => 'nullable|array',
            'capabilities.*' => 'string',
            'metadata'       => 'nullable|array',
        ]);

        try {
            $agentId = Str::uuid()->toString();
            $did = $this->didService->generateDID('agent');

            // Register agent using aggregate
            $aggregate = AgentIdentityAggregate::retrieve($agentId);
            $aggregate->register(
                agentId: $agentId,
                did: $did,
                name: $validated['name'],
                type: $validated['type'],
                metadata: array_merge($validated['metadata'] ?? [], [
                    'description'  => $validated['description'] ?? null,
                    'capabilities' => $validated['capabilities'] ?? [],
                    'owner_id'     => $request->user()?->id,
                ])
            );
            $aggregate->persist();

            // Advertise capabilities if provided
            if (! empty($validated['capabilities'])) {
                foreach ($validated['capabilities'] as $capability) {
                    $capabilityId = $capability . '-' . Str::uuid()->toString();
                    $aggregate->advertiseCapability(
                        capabilityId: $capabilityId,
                        endpoints: [],
                        parameters: [
                            'name'    => $capability,
                            'version' => '1.0',
                        ],
                        requiredPermissions: [],
                        supportedProtocols: ['AP2', 'A2A']
                    );
                }
                $aggregate->persist();
            }

            // Create DID document
            $didDocument = $this->didService->createDIDDocument([
                'did'     => $did,
                'service' => [
                    [
                        'id'              => $did . '#ap2',
                        'type'            => 'AP2Service',
                        'serviceEndpoint' => config('app.url') . '/api/agent-protocol/agents/' . $agentId,
                    ],
                ],
            ]);
            $this->didService->storeDIDDocument($did, $didDocument);

            Log::info('Agent registered', [
                'agent_id' => $agentId,
                'did'      => $did,
                'name'     => $validated['name'],
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'agent_id'      => $agentId,
                    'did'           => $did,
                    'name'          => $validated['name'],
                    'type'          => $validated['type'],
                    'capabilities'  => $validated['capabilities'] ?? [],
                    'registered_at' => now()->toIso8601String(),
                ],
            ], 201);
        } catch (Exception $e) {
            Log::error('Agent registration failed', [
                'error' => $e->getMessage(),
                'name'  => $validated['name'],
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to register agent: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/discover',
            operationId: 'discoverAgents',
            tags: ['Agent Protocol - Identity'],
            summary: 'Discover agents by capabilities or type',
            parameters: [
            new OA\Parameter(name: 'capability', in: 'query', description: 'Filter by capability', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', description: 'Filter by agent type', schema: new OA\Schema(type: 'string', enum: ['service', 'user', 'system'])),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filter by status', schema: new OA\Schema(type: 'string', enum: ['active', 'inactive', 'suspended'])),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Number of results', schema: new OA\Schema(type: 'integer', default: 20)),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'List of discovered agents',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        ])
    )]
    public function discover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'capability' => 'nullable|string',
            'type'       => ['nullable', 'string', Rule::in(['service', 'user', 'system'])],
            'status'     => ['nullable', 'string', Rule::in(['active', 'inactive', 'suspended'])],
            'limit'      => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $agents = $this->agentRegistryService->discoverAgents(
                capability: $validated['capability'] ?? null,
                type: $validated['type'] ?? null,
                status: $validated['status'] ?? 'active',
                limit: $validated['limit'] ?? 20
            );

            return response()->json([
                'success' => true,
                'data'    => $agents,
                'meta'    => [
                    'count'  => count($agents),
                    'filter' => array_filter($validated),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Agent discovery failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => 'Discovery failed: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{did}',
            operationId: 'getAgentByDID',
            tags: ['Agent Protocol - Identity'],
            summary: 'Get agent details by DID',
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Agent details',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Agent not found'
    )]
    public function show(string $did): JsonResponse
    {
        try {
            // Validate DID format
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid DID format',
                ], 400);
            }

            $agent = $this->agentRegistryService->getAgentByDID($did);

            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Agent not found',
                ], 404);
            }

            // Resolve DID document
            $didDocument = $this->didService->resolveDID($did);

            return response()->json([
                'success' => true,
                'data'    => [
                    'agent'        => $agent,
                    'did_document' => $didDocument,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Get agent failed', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to get agent: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Put(
            path: '/api/agent-protocol/agents/{did}/capabilities',
            operationId: 'updateAgentCapabilities',
            tags: ['Agent Protocol - Identity'],
            summary: 'Update agent capabilities',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['capabilities'], properties: [
            new OA\Property(property: 'capabilities', type: 'array', items: new OA\Items(type: 'object', properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'version', type: 'string'),
            new OA\Property(property: 'metadata', type: 'object'),
            ])),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Capabilities updated'
    )]
    #[OA\Response(
        response: 404,
        description: 'Agent not found'
    )]
    #[OA\Response(
        response: 403,
        description: 'Not authorized'
    )]
    public function updateCapabilities(Request $request, string $did): JsonResponse
    {
        $validated = $request->validate([
            'capabilities'            => 'required|array|min:1',
            'capabilities.*.name'     => 'required|string',
            'capabilities.*.version'  => 'required|string',
            'capabilities.*.metadata' => 'nullable|array',
        ]);

        try {
            // Validate DID format
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid DID format',
                ], 400);
            }

            $agent = $this->agentRegistryService->getAgentByDID($did);

            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Agent not found',
                ], 404);
            }

            // Update capabilities using aggregate
            $aggregate = AgentIdentityAggregate::retrieve($agent['agent_id']);

            foreach ($validated['capabilities'] as $capability) {
                $capabilityId = $capability['name'] . '-' . Str::uuid()->toString();
                $aggregate->advertiseCapability(
                    capabilityId: $capabilityId,
                    endpoints: [],
                    parameters: [
                        'name'     => $capability['name'],
                        'version'  => $capability['version'],
                        'metadata' => $capability['metadata'] ?? [],
                    ],
                    requiredPermissions: [],
                    supportedProtocols: ['AP2', 'A2A']
                );
            }

            $aggregate->persist();

            Log::info('Agent capabilities updated', [
                'did'          => $did,
                'capabilities' => array_column($validated['capabilities'], 'name'),
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'did'          => $did,
                    'capabilities' => $validated['capabilities'],
                    'updated_at'   => now()->toIso8601String(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Update capabilities failed', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to update capabilities: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/.well-known/ap2-configuration',
            operationId: 'ap2Configuration',
            tags: ['Agent Protocol - Identity'],
            summary: 'AP2 well-known configuration endpoint'
        )]
    #[OA\Response(
        response: 200,
        description: 'AP2 configuration',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'issuer', type: 'string'),
        new OA\Property(property: 'agent_registration_endpoint', type: 'string'),
        new OA\Property(property: 'agent_discovery_endpoint', type: 'string'),
        new OA\Property(property: 'payment_endpoint', type: 'string'),
        new OA\Property(property: 'escrow_endpoint', type: 'string'),
        new OA\Property(property: 'supported_capabilities', type: 'array', items: new OA\Items(type: 'string')),
        ])
    )]
    public function wellKnownConfiguration(): JsonResponse
    {
        return response()->json([
            'issuer'                      => config('app.url'),
            'agent_registration_endpoint' => config('app.url') . '/api/agent-protocol/agents/register',
            'agent_discovery_endpoint'    => config('app.url') . '/api/agent-protocol/agents/discover',
            'payment_endpoint'            => config('app.url') . '/api/agent-protocol/payments',
            'escrow_endpoint'             => config('app.url') . '/api/agent-protocol/escrow',
            'message_endpoint'            => config('app.url') . '/api/agent-protocol/messages',
            'reputation_endpoint'         => config('app.url') . '/api/agent-protocol/reputation',
            'supported_capabilities'      => [
                'payment',
                'escrow',
                'messaging',
                'trading',
                'compliance',
                'reputation',
            ],
            'supported_protocols' => [
                'AP2/1.0',
                'A2A/1.0',
            ],
            'documentation' => config('app.url') . '/api/documentation',
        ]);
    }
}
