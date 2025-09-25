<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\AgentTransactionAggregate;
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\ValueObjects\AgentIdentifier;
use App\Domain\AgentProtocol\ValueObjects\TransactionAmount;
use App\Domain\AgentProtocol\Workflows\PaymentOrchestrationWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentProtocol\ConfirmPaymentRequest;
use App\Http\Requests\AgentProtocol\InitiatePaymentRequest;
use App\Http\Resources\AgentProtocol\PaymentResource;
use App\Http\Resources\AgentProtocol\PaymentStatusResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

class AgentPaymentController extends Controller
{
    public function __construct(
        private readonly AgentRegistryService $registryService
    ) {
    }

    /**
     * Initiate a payment between agents.
     *
     * @OA\Post(
     *     path="/api/agents/{did}/payments",
     *     operationId="initiateAgentPayment",
     *     tags={"Agent Protocol - Payments"},
     *     summary="Initiate agent payment",
     *     description="Initiate a payment from one agent to another",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="did",
     *         in="path",
     *         description="Sender agent DID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/InitiatePaymentRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Payment initiated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/PaymentResource")
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Agent not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function initiatePayment(InitiatePaymentRequest $request, string $did): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Verify sender agent exists
            $senderAgent = $this->registryService->getAgentByDID($did);
            if (! $senderAgent) {
                return response()->json([
                    'error' => 'Sender agent not found',
                    'did'   => $did,
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

            $transactionId = Str::uuid()->toString();

            // Create transaction aggregate
            $fromAgent = new AgentIdentifier($senderAgent->agent_id, $senderAgent->did);
            $toAgent = new AgentIdentifier($receiverAgent->agent_id, $receiverAgent->did);
            $amount = new TransactionAmount($request->amount, $request->currency ?? 'USD');

            $transactionAggregate = AgentTransactionAggregate::initiate(
                transactionId: $transactionId,
                fromAgent: $fromAgent,
                toAgent: $toAgent,
                amount: $amount,
                metadata: array_merge(
                    $request->metadata ?? [],
                    ['description' => $request->description ?? '']
                )
            );

            $transactionAggregate->persist();

            // Prepare payment request
            $paymentRequest = new AgentPaymentRequest(
                fromAgentDid: $senderAgent->did,
                toAgentDid: $receiverAgent->did,
                amount: $request->amount,
                currency: $request->currency ?? 'USD',
                purpose: $request->description ?? 'payment',
                metadata: array_merge($request->metadata ?? [], [
                    'sender_did'      => $did,
                    'receiver_did'    => $request->receiver_did,
                    'idempotency_key' => $request->idempotency_key ?? null,
                    'description'     => $request->description ?? '',
                ]),
                escrowConditions: null,
                splits: $request->split_payments ?? [],
                timeoutSeconds: 300,
                transactionId: $transactionId
            );

            // Start payment workflow
            $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
            $workflow->start($paymentRequest);

            DB::commit();

            Log::info('Agent payment initiated', [
                'transaction_id' => $transactionId,
                'sender_did'     => $did,
                'receiver_did'   => $request->receiver_did,
                'amount'         => $request->amount,
                'currency'       => $request->currency ?? 'USD',
            ]);

            return response()->json([
                'transaction_id' => $transactionId,
                'status'         => 'pending',
                'sender_did'     => $did,
                'receiver_did'   => $request->receiver_did,
                'amount'         => $request->amount,
                'currency'       => $request->currency ?? 'USD',
                'created_at'     => now()->toIso8601String(),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to initiate agent payment', [
                'error'      => $e->getMessage(),
                'sender_did' => $did,
            ]);

            return response()->json([
                'error'   => 'Failed to initiate payment',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get payment status.
     *
     * @OA\Get(
     *     path="/api/agents/{did}/payments/{id}",
     *     operationId="getAgentPaymentStatus",
     *     tags={"Agent Protocol - Payments"},
     *     summary="Get payment status",
     *     description="Get the status of an agent payment",
     *
     *     @OA\Parameter(
     *         name="did",
     *         in="path",
     *         description="Agent DID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment transaction ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payment status retrieved",
     *         @OA\JsonContent(ref="#/components/schemas/PaymentStatusResource")
     *     ),
     *     @OA\Response(response=404, description="Payment not found")
     * )
     */
    public function getPaymentStatus(string $did, string $id): JsonResponse
    {
        try {
            // Verify agent exists
            $agent = $this->registryService->getAgentByDID($did);
            if (! $agent) {
                return response()->json([
                    'error' => 'Agent not found',
                    'did'   => $did,
                ], Response::HTTP_NOT_FOUND);
            }

            // Load transaction aggregate
            $transactionAggregate = AgentTransactionAggregate::retrieve($id);

            // Verify agent is involved in the transaction
            if (
                $transactionAggregate->getSenderAgentId() !== $agent->agent_id &&
                $transactionAggregate->getReceiverAgentId() !== $agent->agent_id
            ) {
                return response()->json([
                    'error'          => 'Payment not found or unauthorized',
                    'transaction_id' => $id,
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'transaction_id'    => $id,
                'status'            => $transactionAggregate->getStatus(),
                'sender_agent_id'   => $transactionAggregate->getSenderAgentId(),
                'receiver_agent_id' => $transactionAggregate->getReceiverAgentId(),
                'amount'            => $transactionAggregate->getAmount(),
                'currency'          => $transactionAggregate->getCurrency(),
                'created_at'        => $transactionAggregate->getCreatedAt(),
                'updated_at'        => $transactionAggregate->getUpdatedAt(),
                'metadata'          => $transactionAggregate->getMetadata(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get payment status', [
                'error'          => $e->getMessage(),
                'transaction_id' => $id,
            ]);

            return response()->json([
                'error'          => 'Payment not found',
                'transaction_id' => $id,
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Confirm a payment.
     *
     * @OA\Post(
     *     path="/api/agents/{did}/payments/{id}/confirm",
     *     operationId="confirmAgentPayment",
     *     tags={"Agent Protocol - Payments"},
     *     summary="Confirm agent payment",
     *     description="Confirm a pending agent payment",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="did",
     *         in="path",
     *         description="Agent DID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment transaction ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ConfirmPaymentRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payment confirmed successfully",
     *         @OA\JsonContent(ref="#/components/schemas/PaymentResource")
     *     ),
     *     @OA\Response(response=404, description="Payment not found"),
     *     @OA\Response(response=409, description="Payment cannot be confirmed")
     * )
     */
    public function confirmPayment(ConfirmPaymentRequest $request, string $did, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Verify agent exists
            $agent = $this->registryService->getAgentByDID($did);
            if (! $agent) {
                return response()->json([
                    'error' => 'Agent not found',
                    'did'   => $did,
                ], Response::HTTP_NOT_FOUND);
            }

            // Load transaction aggregate
            $transactionAggregate = AgentTransactionAggregate::retrieve($id);

            // Verify agent is the receiver
            if ($transactionAggregate->getReceiverAgentId() !== $agent->agent_id) {
                return response()->json([
                    'error'          => 'Only receiver can confirm payment',
                    'transaction_id' => $id,
                ], Response::HTTP_FORBIDDEN);
            }

            // Confirm the transaction
            $transactionAggregate->confirm(
                confirmedBy: $agent->agent_id,
                confirmationCode: $request->confirmation_code ?? null
            );

            $transactionAggregate->persist();

            DB::commit();

            Log::info('Agent payment confirmed', [
                'transaction_id' => $id,
                'confirmed_by'   => $did,
            ]);

            return response()->json([
                'transaction_id' => $id,
                'status'         => 'confirmed',
                'message'        => 'Payment confirmed successfully',
                'confirmed_at'   => now()->toIso8601String(),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to confirm payment', [
                'error'          => $e->getMessage(),
                'transaction_id' => $id,
            ]);

            return response()->json([
                'error'   => 'Failed to confirm payment',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancel a payment.
     *
     * @OA\Post(
     *     path="/api/agents/{did}/payments/{id}/cancel",
     *     operationId="cancelAgentPayment",
     *     tags={"Agent Protocol - Payments"},
     *     summary="Cancel agent payment",
     *     description="Cancel a pending agent payment",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="did",
     *         in="path",
     *         description="Agent DID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment transaction ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payment cancelled successfully",
     *         @OA\JsonContent(ref="#/components/schemas/PaymentResource")
     *     ),
     *     @OA\Response(response=404, description="Payment not found"),
     *     @OA\Response(response=409, description="Payment cannot be cancelled")
     * )
     */
    public function cancelPayment(string $did, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Verify agent exists
            $agent = $this->registryService->getAgentByDID($did);
            if (! $agent) {
                return response()->json([
                    'error' => 'Agent not found',
                    'did'   => $did,
                ], Response::HTTP_NOT_FOUND);
            }

            // Load transaction aggregate
            $transactionAggregate = AgentTransactionAggregate::retrieve($id);

            // Verify agent is the sender
            if ($transactionAggregate->getSenderAgentId() !== $agent->agent_id) {
                return response()->json([
                    'error'          => 'Only sender can cancel payment',
                    'transaction_id' => $id,
                ], Response::HTTP_FORBIDDEN);
            }

            // Cancel the transaction
            $transactionAggregate->cancel(
                reason: 'Cancelled by sender',
                cancelledBy: $agent->agent_id
            );

            $transactionAggregate->persist();

            DB::commit();

            Log::info('Agent payment cancelled', [
                'transaction_id' => $id,
                'cancelled_by'   => $did,
            ]);

            return response()->json([
                'transaction_id' => $id,
                'status'         => 'cancelled',
                'message'        => 'Payment cancelled successfully',
                'cancelled_at'   => now()->toIso8601String(),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel payment', [
                'error'          => $e->getMessage(),
                'transaction_id' => $id,
            ]);

            return response()->json([
                'error'   => 'Failed to cancel payment',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
