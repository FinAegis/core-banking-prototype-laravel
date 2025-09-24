<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use App\Domain\AgentProtocol\Services\AgentDiscoveryService;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Services\DIDService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentProtocol\RegisterAgentRequest;
use App\Http\Requests\AgentProtocol\UpdateCapabilitiesRequest;
use App\Http\Resources\AgentProtocol\AgentDiscoveryResource;
use App\Http\Resources\AgentProtocol\AgentResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentRegistrationController extends Controller
{
    public function __construct(
        private readonly AgentRegistryService $registryService,
        private readonly AgentDiscoveryService $discoveryService,
        private readonly DIDService $didService
    ) {
    }

    /**
     * Register a new agent.
     *
     * @OA\Post(
     *     path="/api/agents/register",
     *     operationId="registerAgent",
     *     tags={"Agent Protocol - Registration"},
     *     summary="Register a new AI agent",
     *     description="Registers a new AI agent with the platform, generates DID, and creates a wallet",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/RegisterAgentRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Agent successfully registered",
     *         @OA\JsonContent(ref="#/components/schemas/AgentResource")
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(RegisterAgentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Generate DID if not provided
            $did = $request->did ?? $this->didService->generateDID();
            $agentId = Str::uuid()->toString();

            // Create agent identity aggregate
            $identityAggregate = AgentIdentityAggregate::register(
                agentId: $agentId,
                did: $did,
                name: $request->name,
                type: $request->type ?? 'autonomous',
                metadata: $request->metadata ?? []
            );

            // Create wallet for the agent
            $walletId = Str::uuid()->toString();
            $identityAggregate->createWallet(
                walletId: $walletId,
                currency: $request->default_currency ?? 'USD',
                initialBalance: 0.0,
                metadata: ['primary' => true]
            );

            // Advertise initial capabilities if provided
            if ($request->capabilities) {
                foreach ($request->capabilities as $capability) {
                    $identityAggregate->advertiseCapability(
                        capabilityId: $capability['id'],
                        endpoints: $capability['endpoints'] ?? [],
                        parameters: $capability['parameters'] ?? [],
                        requiredPermissions: $capability['required_permissions'] ?? [],
                        supportedProtocols: $capability['supported_protocols'] ?? ['AP2', 'A2A']
                    );
                }
            }

            $identityAggregate->persist();

            // Register in the registry service
            $agent = $this->registryService->registerAgent([
                'agentId'      => $agentId,
                'did'          => $did,
                'name'         => $request->name,
                'type'         => $request->type ?? 'autonomous',
                'networkId'    => $request->network_id ?? null,
                'organization' => $request->organization ?? null,
                'endpoints'    => $request->endpoints ?? [],
                'capabilities' => array_map(fn ($c) => $c['id'], $request->capabilities ?? []),
                'metadata'     => array_merge($request->metadata ?? [], [
                    'wallet_id'     => $walletId,
                    'registered_at' => now()->toIso8601String(),
                ]),
            ]);

            DB::commit();

            Log::info('Agent registered successfully', [
                'agent_id' => $agentId,
                'did'      => $did,
                'name'     => $request->name,
            ]);

            return response()->json(
                new AgentResource($agent),
                Response::HTTP_CREATED
            );
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to register agent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error'   => 'Failed to register agent',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Discover agents.
     *
     * @OA\Get(
     *     path="/api/agents/discover",
     *     operationId="discoverAgents",
     *     tags={"Agent Protocol - Registration"},
     *     summary="Discover available agents",
     *     description="Search and discover agents based on capabilities, organization, or other criteria",
     *
     *     @OA\Parameter(
     *         name="capability",
     *         in="query",
     *         description="Filter by capability",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="organization",
     *         in="query",
     *         description="Filter by organization",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by agent type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"autonomous", "assistant", "service", "gateway"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of discovered agents",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/AgentDiscoveryResource"))
     *     )
     * )
     */
    public function discover(Request $request): JsonResponse
    {
        $filters = [
            'capability'   => $request->query('capability'),
            'organization' => $request->query('organization'),
            'type'         => $request->query('type'),
            'status'       => 'active', // Only show active agents
        ];

        $agents = $this->discoveryService->discoverAgents(array_filter($filters));

        return response()->json(
            AgentDiscoveryResource::collection($agents)
        );
    }

    /**
     * Get agent details.
     *
     * @OA\Get(
     *     path="/api/agents/{did}",
     *     operationId="getAgentDetails",
     *     tags={"Agent Protocol - Registration"},
     *     summary="Get agent details by DID",
     *     description="Retrieve detailed information about an agent using its DID",
     *
     *     @OA\Parameter(
     *         name="did",
     *         in="path",
     *         description="Agent DID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Agent details",
     *         @OA\JsonContent(ref="#/components/schemas/AgentResource")
     *     ),
     *     @OA\Response(response=404, description="Agent not found")
     * )
     */
    public function show(string $did): JsonResponse
    {
        $agent = $this->registryService->getAgentByDID($did);

        if (! $agent) {
            return response()->json([
                'error' => 'Agent not found',
                'did'   => $did,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            new AgentResource($agent)
        );
    }

    /**
     * Update agent capabilities.
     *
     * @OA\Put(
     *     path="/api/agents/{did}/capabilities",
     *     operationId="updateAgentCapabilities",
     *     tags={"Agent Protocol - Registration"},
     *     summary="Update agent capabilities",
     *     description="Update or add capabilities for an existing agent",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="did",
     *         in="path",
     *         description="Agent DID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateCapabilitiesRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Capabilities updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/AgentResource")
     *     ),
     *     @OA\Response(response=404, description="Agent not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateCapabilities(UpdateCapabilitiesRequest $request, string $did): JsonResponse
    {
        try {
            DB::beginTransaction();

            $agent = $this->registryService->getAgentByDID($did);
            if (! $agent) {
                return response()->json([
                    'error' => 'Agent not found',
                    'did'   => $did,
                ], Response::HTTP_NOT_FOUND);
            }

            // Load the agent identity aggregate
            $identityAggregate = AgentIdentityAggregate::retrieve($agent->agent_id);

            // Update capabilities
            foreach ($request->capabilities as $capability) {
                if ($request->action === 'add' || $request->action === 'update') {
                    $identityAggregate->advertiseCapability(
                        capabilityId: $capability['id'],
                        endpoints: $capability['endpoints'] ?? [],
                        parameters: $capability['parameters'] ?? [],
                        requiredPermissions: $capability['required_permissions'] ?? [],
                        supportedProtocols: $capability['supported_protocols'] ?? ['AP2', 'A2A']
                    );
                }
                // Note: For 'remove' action, we would need to implement a removeCapability method
            }

            $identityAggregate->persist();

            // Update the registry
            $currentCapabilities = $agent->capabilities ?? [];
            if ($request->action === 'add') {
                $newCapabilities = array_unique(array_merge(
                    $currentCapabilities,
                    array_map(fn ($c) => $c['id'], $request->capabilities)
                ));
            } elseif ($request->action === 'update') {
                $newCapabilities = array_map(fn ($c) => $c['id'], $request->capabilities);
            } else { // remove
                $toRemove = array_map(fn ($c) => $c['id'], $request->capabilities);
                $newCapabilities = array_diff($currentCapabilities, $toRemove);
            }

            $agent->capabilities = array_values($newCapabilities);
            $agent->save();

            DB::commit();

            Log::info('Agent capabilities updated', [
                'agent_id'     => $agent->agent_id,
                'did'          => $did,
                'action'       => $request->action,
                'capabilities' => $newCapabilities,
            ]);

            return response()->json(
                new AgentResource($agent)
            );
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update agent capabilities', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'Failed to update capabilities',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
