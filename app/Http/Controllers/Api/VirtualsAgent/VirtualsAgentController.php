<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\VirtualsAgent;

use App\Domain\VirtualsAgent\DataObjects\AgentOnboardingRequest;
use App\Domain\VirtualsAgent\Enums\AgentStatus;
use App\Domain\VirtualsAgent\Events\VirtualsAgentActivated;
use App\Domain\VirtualsAgent\Models\VirtualsAgentProfile;
use App\Domain\VirtualsAgent\Services\AgdpReportingService;
use App\Domain\VirtualsAgent\Services\AgentOnboardingService;
use App\Domain\VirtualsAgent\Services\VirtualsAgentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use RuntimeException;

class VirtualsAgentController extends Controller
{
    public function __construct(
        private readonly AgentOnboardingService $onboardingService,
        private readonly VirtualsAgentService $agentService,
        private readonly AgdpReportingService $agdpReportingService,
    ) {
    }

    /**
     * List all agents belonging to the authenticated user (employer).
     */
    #[OA\Get(
        path: '/api/v1/virtuals-agents',
        operationId: 'virtualsAgentIndex',
        summary: 'List employer agents',
        description: 'Returns all Virtuals Protocol agents registered by the authenticated user.',
        tags: ['Virtuals Agents'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'success', type: 'boolean', example: true),
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'virtualsAgentId', type: 'string'),
                new OA\Property(property: 'agentName', type: 'string'),
                new OA\Property(property: 'status', type: 'string', enum: ['registered', 'active', 'suspended', 'deactivated']),
                new OA\Property(property: 'chain', type: 'string'),
            ])),
        ])
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $agents = $this->onboardingService->getEmployerAgents($user->id);

        return response()->json([
            'success' => true,
            'data'    => $agents->map(fn (VirtualsAgentProfile $agent) => $agent->toApiResponse())->values()->all(),
        ]);
    }

    /**
     * Onboard a new Virtuals Protocol agent.
     */
    #[OA\Post(
        path: '/api/v1/virtuals-agents/onboard',
        operationId: 'virtualsAgentOnboard',
        summary: 'Onboard a new Virtuals agent',
        description: 'Registers a Virtuals Protocol agent with FinAegis, provisions spending limits and TrustCert credentials.',
        tags: ['Virtuals Agents'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['virtuals_agent_id', 'agent_name'],
            properties: [
                new OA\Property(property: 'virtuals_agent_id', type: 'string', maxLength: 255, example: 'agent_abc123'),
                new OA\Property(property: 'agent_name', type: 'string', maxLength: 255, example: 'Treasury Bot'),
                new OA\Property(property: 'agent_description', type: 'string', nullable: true, example: 'Handles daily treasury operations'),
                new OA\Property(property: 'chain', type: 'string', enum: ['base', 'polygon', 'arbitrum', 'ethereum'], example: 'base'),
                new OA\Property(property: 'daily_limit_cents', type: 'integer', minimum: 1, nullable: true, example: 50000),
                new OA\Property(property: 'per_tx_limit_cents', type: 'integer', minimum: 1, nullable: true, example: 10000),
            ]
        ))
    )]
    #[OA\Response(
        response: 200,
        description: 'Agent onboarded successfully',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'success', type: 'boolean', example: true),
            new OA\Property(property: 'data', type: 'object'),
        ])
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 422, description: 'Validation error or onboarding failure')]
    public function onboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'virtuals_agent_id'  => ['required', 'string', 'max:255'],
            'agent_name'         => ['required', 'string', 'max:255'],
            'agent_description'  => ['nullable', 'string'],
            'chain'              => ['string', 'in:base,polygon,arbitrum,ethereum'],
            'daily_limit_cents'  => ['nullable', 'integer', 'min:1'],
            'per_tx_limit_cents' => ['nullable', 'integer', 'min:1'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $onboardingRequest = new AgentOnboardingRequest(
            virtualsAgentId: $validated['virtuals_agent_id'],
            employerUserId: $user->id,
            agentName: $validated['agent_name'],
            agentDescription: $validated['agent_description'] ?? null,
            chain: $validated['chain'] ?? 'base',
            dailyLimitCents: isset($validated['daily_limit_cents']) ? (int) $validated['daily_limit_cents'] : null,
            perTxLimitCents: isset($validated['per_tx_limit_cents']) ? (int) $validated['per_tx_limit_cents'] : null,
        );

        try {
            $profile = $this->onboardingService->onboardAgent($onboardingRequest);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ONBOARDING_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => $profile->toApiResponse(),
        ]);
    }

    /**
     * Show a single agent with spending summary.
     */
    #[OA\Get(
        path: '/api/v1/virtuals-agents/{id}',
        operationId: 'virtualsAgentShow',
        summary: 'Get agent details with spending summary',
        description: 'Returns a single Virtuals agent profile with spending summary. Requires ownership.',
        tags: ['Virtuals Agents'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Agent profile UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'success', type: 'boolean', example: true),
            new OA\Property(property: 'data', type: 'object', properties: [
                new OA\Property(property: 'profile', type: 'object'),
                new OA\Property(property: 'spending', type: 'object', properties: [
                    new OA\Property(property: 'daily_limit', type: 'integer'),
                    new OA\Property(property: 'spent_today', type: 'integer'),
                    new OA\Property(property: 'remaining', type: 'integer'),
                    new OA\Property(property: 'last_transactions', type: 'array', items: new OA\Items(type: 'object')),
                ]),
            ]),
        ])
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden — not the agent owner')]
    #[OA\Response(response: 404, description: 'Agent not found')]
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $profile = VirtualsAgentProfile::find($id);

        if ($profile === null) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Agent not found.'],
            ], 404);
        }

        if ($profile->employer_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'You do not own this agent.'],
            ], 403);
        }

        $spending = $this->agentService->getAgentSpendingSummary($profile->virtuals_agent_id);

        return response()->json([
            'success' => true,
            'data'    => [
                'profile'  => $profile->toApiResponse(),
                'spending' => $spending,
            ],
        ]);
    }

    /**
     * Suspend an active agent.
     */
    #[OA\Put(
        path: '/api/v1/virtuals-agents/{id}/suspend',
        operationId: 'virtualsAgentSuspend',
        summary: 'Suspend an agent',
        description: 'Suspends an active Virtuals agent. Requires ownership.',
        tags: ['Virtuals Agents'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Agent profile UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Agent suspended',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'success', type: 'boolean', example: true),
            new OA\Property(property: 'data', type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Agent suspended successfully.'),
            ]),
        ])
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden')]
    #[OA\Response(response: 404, description: 'Agent not found')]
    #[OA\Response(response: 422, description: 'Agent cannot be suspended')]
    public function suspend(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $profile = VirtualsAgentProfile::find($id);

        if ($profile === null) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Agent not found.'],
            ], 404);
        }

        if ($profile->employer_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'You do not own this agent.'],
            ], 403);
        }

        $result = $this->onboardingService->suspendAgent($profile->id, 'Suspended by employer via API');

        if (! $result) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'SUSPEND_FAILED', 'message' => 'Agent cannot be suspended in its current state.'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => ['message' => 'Agent suspended successfully.'],
        ]);
    }

    /**
     * Activate a suspended or registered agent.
     */
    #[OA\Put(
        path: '/api/v1/virtuals-agents/{id}/activate',
        operationId: 'virtualsAgentActivate',
        summary: 'Activate an agent',
        description: 'Activates a suspended or registered Virtuals agent. Requires ownership.',
        tags: ['Virtuals Agents'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Agent profile UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Agent activated',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'success', type: 'boolean', example: true),
            new OA\Property(property: 'data', type: 'object', properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Agent activated successfully.'),
            ]),
        ])
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden')]
    #[OA\Response(response: 404, description: 'Agent not found')]
    #[OA\Response(response: 422, description: 'Agent cannot be activated')]
    public function activate(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $profile = VirtualsAgentProfile::find($id);

        if ($profile === null) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Agent not found.'],
            ], 404);
        }

        if ($profile->employer_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'You do not own this agent.'],
            ], 403);
        }

        $status = $profile->status instanceof AgentStatus ? $profile->status : AgentStatus::tryFrom((string) $profile->status);

        if ($status === AgentStatus::DEACTIVATED) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'ACTIVATE_FAILED', 'message' => 'Deactivated agents cannot be reactivated.'],
            ], 422);
        }

        if ($status === AgentStatus::ACTIVE) {
            return response()->json([
                'success' => true,
                'data'    => ['message' => 'Agent is already active.'],
            ]);
        }

        $profile->update(['status' => AgentStatus::ACTIVE]);

        event(new VirtualsAgentActivated(
            agentProfileId: $profile->id,
            virtualsAgentId: $profile->virtuals_agent_id,
        ));

        Log::info('Virtuals agent activated via API', [
            'profile_id'        => $profile->id,
            'virtuals_agent_id' => $profile->virtuals_agent_id,
            'employer_user_id'  => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'data'    => ['message' => 'Agent activated successfully.'],
        ]);
    }

    /**
     * List transactions for a specific agent.
     */
    #[OA\Get(
        path: '/api/v1/virtuals-agents/{id}/transactions',
        operationId: 'virtualsAgentTransactions',
        summary: 'Get agent transactions',
        description: 'Returns recent payment transactions for the given Virtuals agent. Requires ownership.',
        tags: ['Virtuals Agents'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Agent profile UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'success', type: 'boolean', example: true),
            new OA\Property(property: 'data', type: 'object', properties: [
                new OA\Property(property: 'agent_id', type: 'string'),
                new OA\Property(property: 'daily_limit', type: 'integer'),
                new OA\Property(property: 'spent_today', type: 'integer'),
                new OA\Property(property: 'remaining', type: 'integer'),
                new OA\Property(property: 'last_transactions', type: 'array', items: new OA\Items(type: 'object')),
            ]),
        ])
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 403, description: 'Forbidden')]
    #[OA\Response(response: 404, description: 'Agent not found')]
    public function transactions(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $profile = VirtualsAgentProfile::find($id);

        if ($profile === null) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Agent not found.'],
            ], 404);
        }

        if ($profile->employer_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'You do not own this agent.'],
            ], 403);
        }

        $summary = $this->agentService->getAgentSpendingSummary($profile->virtuals_agent_id);

        return response()->json([
            'success' => true,
            'data'    => array_merge(['agent_id' => $profile->virtuals_agent_id], $summary),
        ]);
    }

    /**
     * Get aGDP (Agent Gross Domestic Product) metrics.
     */
    #[OA\Get(
        path: '/api/v1/virtuals-agents/agdp',
        operationId: 'virtualsAgentAgdp',
        summary: 'Get aGDP metrics',
        description: 'Returns aggregate Agent GDP metrics — total payments, active agents, and transaction counts across all Virtuals agents.',
        tags: ['Virtuals Agents'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'period', in: 'query', required: false, description: 'Reporting period', schema: new OA\Schema(type: 'string', enum: ['1h', '24h', '7d', '30d'], default: '24h')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'success', type: 'boolean', example: true),
            new OA\Property(property: 'data', type: 'object', properties: [
                new OA\Property(property: 'totalPaymentsCents', type: 'integer'),
                new OA\Property(property: 'totalTransactions', type: 'integer'),
                new OA\Property(property: 'activeAgents', type: 'integer'),
                new OA\Property(property: 'totalAgents', type: 'integer'),
                new OA\Property(property: 'period', type: 'string'),
                new OA\Property(property: 'calculatedAt', type: 'string', format: 'date-time'),
            ]),
        ])
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    public function agdp(Request $request): JsonResponse
    {
        $period = $request->query('period', '24h');
        $period = is_string($period) ? $period : '24h';

        $validPeriods = ['1h', '24h', '7d', '30d'];
        if (! in_array($period, $validPeriods, true)) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_PERIOD',
                    'message' => 'Invalid period. Must be one of: ' . implode(', ', $validPeriods),
                ],
            ], 422);
        }

        $metrics = $this->agdpReportingService->getMetrics($period);

        return response()->json([
            'success' => true,
            'data'    => $metrics->toArray(),
        ]);
    }
}
