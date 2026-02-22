<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\X402;

use App\Domain\X402\Models\X402SpendingLimit;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * X402 Agent Spending Limit Controller.
 *
 * Manages per-agent spending limits for the x402 client mode
 * (AI agents making payments to external APIs).
 *
 * @OA\Tag(
 *     name="X402 Spending Limits",
 *     description="Agent spending limit management for x402 payments"
 * )
 */
class X402SpendingLimitController extends Controller
{
    /**
     * List all agent spending limits.
     *
     * @OA\Get(
     *     path="/api/v1/x402/spending-limits",
     *     summary="List agent spending limits",
     *     tags={"X402 Spending Limits"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=50, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="List of spending limits"
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        $limits = X402SpendingLimit::where('team_id', $request->user()?->currentTeam?->id)
            ->orderBy('agent_id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($limits->items())->map(fn ($l) => $l->toApiResponse()),
            'meta' => [
                'current_page' => $limits->currentPage(),
                'last_page'    => $limits->lastPage(),
                'per_page'     => $limits->perPage(),
                'total'        => $limits->total(),
            ],
        ]);
    }

    /**
     * Create or update an agent spending limit.
     *
     * @OA\Post(
     *     path="/api/v1/x402/spending-limits",
     *     summary="Set spending limit for an agent",
     *     tags={"X402 Spending Limits"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"agent_id", "daily_limit"},
     *             @OA\Property(property="agent_id", type="string"),
     *             @OA\Property(property="agent_type", type="string", example="ai_agent"),
     *             @OA\Property(property="daily_limit", type="string", example="10000000"),
     *             @OA\Property(property="per_transaction_limit", type="string", example="1000000"),
     *             @OA\Property(property="auto_pay_enabled", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Spending limit created"),
     *     @OA\Response(response=200, description="Spending limit updated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'agent_id'              => ['required', 'string', 'max:255'],
            'agent_type'            => ['nullable', 'string', 'max:100'],
            'daily_limit'           => ['required', 'string', 'regex:/^\d+$/'],
            'per_transaction_limit' => ['nullable', 'string', 'regex:/^\d+$/'],
            'auto_pay_enabled'      => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $teamId = $request->user()?->currentTeam?->id;

        $limit = X402SpendingLimit::updateOrCreate(
            [
                'agent_id' => $request->input('agent_id'),
                'team_id'  => $teamId,
            ],
            [
                'agent_type'            => $request->input('agent_type', 'default'),
                'daily_limit'           => $request->input('daily_limit'),
                'per_transaction_limit' => $request->input('per_transaction_limit', config('x402.agent_spending.default_per_transaction_limit')),
                'auto_pay_enabled'      => $request->boolean('auto_pay_enabled', false),
                'limit_resets_at'       => now()->addDay(),
            ]
        );

        $statusCode = $limit->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'data'    => $limit->toApiResponse(),
            'message' => $limit->wasRecentlyCreated ? 'Spending limit created.' : 'Spending limit updated.',
        ], $statusCode);
    }

    /**
     * Get spending limit for an agent.
     *
     * @OA\Get(
     *     path="/api/v1/x402/spending-limits/{agentId}",
     *     summary="Get agent spending limit details",
     *     tags={"X402 Spending Limits"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="agentId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Spending limit details"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request, string $agentId): JsonResponse
    {
        $limit = X402SpendingLimit::where('agent_id', $agentId)
            ->where('team_id', $request->user()?->currentTeam?->id)
            ->firstOrFail();

        return response()->json([
            'data' => $limit->toApiResponse(),
        ]);
    }

    /**
     * Update an agent spending limit.
     *
     * @OA\Put(
     *     path="/api/v1/x402/spending-limits/{agentId}",
     *     summary="Update agent spending limit",
     *     tags={"X402 Spending Limits"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="agentId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="daily_limit", type="string"),
     *             @OA\Property(property="per_transaction_limit", type="string"),
     *             @OA\Property(property="auto_pay_enabled", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Spending limit updated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, string $agentId): JsonResponse
    {
        $limit = X402SpendingLimit::where('agent_id', $agentId)
            ->where('team_id', $request->user()?->currentTeam?->id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'daily_limit'           => ['sometimes', 'string', 'regex:/^\d+$/'],
            'per_transaction_limit' => ['sometimes', 'nullable', 'string', 'regex:/^\d+$/'],
            'auto_pay_enabled'      => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $limit->update($validator->validated());

        return response()->json([
            'data'    => $limit->fresh()->toApiResponse(),
            'message' => 'Spending limit updated.',
        ]);
    }

    /**
     * Delete an agent spending limit.
     *
     * @OA\Delete(
     *     path="/api/v1/x402/spending-limits/{agentId}",
     *     summary="Remove agent spending limit",
     *     tags={"X402 Spending Limits"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="agentId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Spending limit removed"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(Request $request, string $agentId): JsonResponse
    {
        $limit = X402SpendingLimit::where('agent_id', $agentId)
            ->where('team_id', $request->user()?->currentTeam?->id)
            ->firstOrFail();
        $limit->delete();

        return response()->json([
            'data'    => ['agent_id' => $agentId],
            'message' => 'Spending limit removed.',
        ]);
    }
}
