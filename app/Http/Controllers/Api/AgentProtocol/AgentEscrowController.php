<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\EscrowAggregate;
use App\Domain\AgentProtocol\DataObjects\EscrowRequest;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Workflows\EscrowWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentProtocol\CreateEscrowRequest;
use App\Http\Requests\AgentProtocol\DisputeEscrowRequest;
use App\Http\Requests\AgentProtocol\ReleaseEscrowRequest;
use App\Http\Resources\AgentProtocol\EscrowResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

class AgentEscrowController extends Controller
{
    public function __construct(
        private readonly AgentRegistryService $registryService
    ) {
    }

    /**
     * Create an escrow transaction.
     *
     * @OA\Post(
     *     path="/api/agents/escrow",
     *     operationId="createEscrow",
     *     tags={"Agent Protocol - Escrow"},
     *     summary="Create escrow transaction",
     *     description="Create a new escrow transaction between agents",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateEscrowRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Escrow created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/EscrowResource")
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createEscrow(CreateEscrowRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Verify sender agent exists
            $senderAgent = $this->registryService->getAgentByDID($request->sender_did);
            if (! $senderAgent) {
                return response()->json([
                    'error' => 'Sender agent not found',
                    'did'   => $request->sender_did,
                ], Response::HTTP_NOT_FOUND);
            }

            // Verify receiver agent exists
            $receiverAgent = $this->registryService->getAgentByDID($request->receiver_did);
            if (! $receiverAgent) {
                return response()->json([
                    'error' => 'Receiver agent not found',
                    'did'   => $request->receiver_did,
                ], Response::HTTP_NOT_FOUND);
            }

            $escrowId = Str::uuid()->toString();

            // Create escrow aggregate
            $transactionId = 'txn_' . Str::uuid()->toString();
            $escrowAggregate = EscrowAggregate::create(
                escrowId: $escrowId,
                transactionId: $transactionId,
                senderAgentId: $senderAgent->agent_id,
                receiverAgentId: $receiverAgent->agent_id,
                amount: $request->amount,
                currency: $request->currency ?? 'USD',
                conditions: $request->conditions ?? [],
                expiresAt: $request->expires_at ?? null,
                metadata: $request->metadata ?? []
            );

            $escrowAggregate->persist();

            // Prepare escrow request
            $escrowRequest = new EscrowRequest(
                buyerDid: $senderAgent->did,
                sellerDid: $receiverAgent->did,
                amount: $request->amount,
                currency: $request->currency ?? 'USD',
                conditions: $request->conditions ?? [],
                releaseConditions: $request->release_conditions ?? [],
                disputeResolutionDid: null,
                timeoutSeconds: $request->timeout_seconds ?? 86400,
                metadata: array_merge($request->metadata ?? [], [
                    'sender_did'   => $request->sender_did,
                    'receiver_did' => $request->receiver_did,
                ]),
                escrowId: $escrowId
            );

            // Start escrow workflow
            $workflow = WorkflowStub::make(EscrowWorkflow::class);
            $workflow->start($escrowRequest);

            DB::commit();

            Log::info('Escrow created', [
                'escrow_id'    => $escrowId,
                'sender_did'   => $request->sender_did,
                'receiver_did' => $request->receiver_did,
                'amount'       => $request->amount,
            ]);

            return response()->json([
                'escrow_id'    => $escrowId,
                'status'       => 'created',
                'sender_did'   => $request->sender_did,
                'receiver_did' => $request->receiver_did,
                'amount'       => $request->amount,
                'currency'     => $request->currency ?? 'USD',
                'expires_at'   => $request->expires_at,
                'created_at'   => now()->toIso8601String(),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create escrow', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'Failed to create escrow',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Release escrow funds.
     *
     * @OA\Post(
     *     path="/api/agents/escrow/{id}/release",
     *     operationId="releaseEscrow",
     *     tags={"Agent Protocol - Escrow"},
     *     summary="Release escrow funds",
     *     description="Release funds from escrow to the receiver",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Escrow ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ReleaseEscrowRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Escrow released successfully",
     *         @OA\JsonContent(ref="#/components/schemas/EscrowResource")
     *     ),
     *     @OA\Response(response=404, description="Escrow not found"),
     *     @OA\Response(response=409, description="Escrow cannot be released")
     * )
     */
    public function releaseEscrow(ReleaseEscrowRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Verify agent exists
            $agent = $this->registryService->getAgentByDID($request->agent_did);
            if (! $agent) {
                return response()->json([
                    'error' => 'Agent not found',
                    'did'   => $request->agent_did,
                ], Response::HTTP_NOT_FOUND);
            }

            // Load escrow aggregate
            $escrowAggregate = EscrowAggregate::retrieve($id);

            // Verify agent is authorized to release
            if (
                $escrowAggregate->getSenderAgentId() !== $agent->agent_id &&
                ! $escrowAggregate->isArbiter($agent->agent_id)
            ) {
                return response()->json([
                    'error'     => 'Unauthorized to release escrow',
                    'escrow_id' => $id,
                ], Response::HTTP_FORBIDDEN);
            }

            // Release the escrow
            $escrowAggregate->release(
                releasedBy: $agent->agent_id,
                reason: $request->reason ?? 'Conditions met'
            );

            $escrowAggregate->persist();

            DB::commit();

            Log::info('Escrow released', [
                'escrow_id'   => $id,
                'released_by' => $request->agent_did,
            ]);

            return response()->json([
                'escrow_id'   => $id,
                'status'      => 'released',
                'message'     => 'Escrow released successfully',
                'released_at' => now()->toIso8601String(),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to release escrow', [
                'error'     => $e->getMessage(),
                'escrow_id' => $id,
            ]);

            return response()->json([
                'error'   => 'Failed to release escrow',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Raise an escrow dispute.
     *
     * @OA\Post(
     *     path="/api/agents/escrow/{id}/dispute",
     *     operationId="disputeEscrow",
     *     tags={"Agent Protocol - Escrow"},
     *     summary="Raise escrow dispute",
     *     description="Raise a dispute for an escrow transaction",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Escrow ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/DisputeEscrowRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Dispute raised successfully",
     *         @OA\JsonContent(ref="#/components/schemas/EscrowResource")
     *     ),
     *     @OA\Response(response=404, description="Escrow not found"),
     *     @OA\Response(response=409, description="Dispute cannot be raised")
     * )
     */
    public function disputeEscrow(DisputeEscrowRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Verify agent exists
            $agent = $this->registryService->getAgentByDID($request->agent_did);
            if (! $agent) {
                return response()->json([
                    'error' => 'Agent not found',
                    'did'   => $request->agent_did,
                ], Response::HTTP_NOT_FOUND);
            }

            // Load escrow aggregate
            $escrowAggregate = EscrowAggregate::retrieve($id);

            // Verify agent is involved in the transaction
            if (
                $escrowAggregate->getSenderAgentId() !== $agent->agent_id &&
                $escrowAggregate->getReceiverAgentId() !== $agent->agent_id
            ) {
                return response()->json([
                    'error'     => 'Only parties involved can raise disputes',
                    'escrow_id' => $id,
                ], Response::HTTP_FORBIDDEN);
            }

            $disputeId = Str::uuid()->toString();

            // Raise dispute
            $escrowAggregate->raiseDispute(
                disputeId: $disputeId,
                raisedBy: $agent->agent_id,
                reason: $request->reason,
                evidence: $request->evidence ?? []
            );

            $escrowAggregate->persist();

            DB::commit();

            Log::info('Escrow dispute raised', [
                'escrow_id'  => $id,
                'dispute_id' => $disputeId,
                'raised_by'  => $request->agent_did,
            ]);

            return response()->json([
                'escrow_id'   => $id,
                'dispute_id'  => $disputeId,
                'status'      => 'disputed',
                'message'     => 'Dispute raised successfully',
                'disputed_at' => now()->toIso8601String(),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to raise dispute', [
                'error'     => $e->getMessage(),
                'escrow_id' => $id,
            ]);

            return response()->json([
                'error'   => 'Failed to raise dispute',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
