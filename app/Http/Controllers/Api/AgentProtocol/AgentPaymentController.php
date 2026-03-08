<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\AgentTransactionAggregate;
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
    name: 'Agent Protocol - Payments',
    description: 'Agent payment initiation and management endpoints'
)]
class AgentPaymentController extends Controller
{
    public function __construct(
        private readonly DIDService $didService,
        private readonly AgentRegistryService $agentRegistryService
    ) {
    }

        #[OA\Post(
            path: '/api/agent-protocol/agents/{did}/payments',
            operationId: 'initiateAgentPayment',
            tags: ['Agent Protocol - Payments'],
            summary: 'Initiate a payment from an agent',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Sender agent DID', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['to_agent_did', 'amount', 'currency'], properties: [
            new OA\Property(property: 'to_agent_did', type: 'string', example: 'did:finaegis:agent:abc123'),
            new OA\Property(property: 'amount', type: 'number', format: 'float', example: 100.50),
            new OA\Property(property: 'currency', type: 'string', example: 'USD'),
            new OA\Property(property: 'description', type: 'string', example: 'Payment for service'),
            new OA\Property(property: 'escrow_required', type: 'boolean', example: false),
            new OA\Property(property: 'metadata', type: 'object'),
            ]))
        )]
    #[OA\Response(
        response: 201,
        description: 'Payment initiated',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'transaction_id', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'amount', type: 'number'),
        new OA\Property(property: 'currency', type: 'string'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid request'
    )]
    #[OA\Response(
        response: 404,
        description: 'Agent not found'
    )]
    #[OA\Response(
        response: 422,
        description: 'Insufficient balance'
    )]
    public function initiatePayment(Request $request, string $did): JsonResponse
    {
        $validated = $request->validate([
            'to_agent_did'    => 'required|string',
            'amount'          => 'required|numeric|min:0.01',
            'currency'        => ['required', 'string', Rule::in(['USD', 'EUR', 'GBP', 'GCU'])],
            'description'     => 'nullable|string|max:500',
            'escrow_required' => 'nullable|boolean',
            'metadata'        => 'nullable|array',
        ]);

        try {
            // Validate sender DID
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid sender DID format',
                ], 400);
            }

            // Validate receiver DID
            if (! $this->didService->validateDID($validated['to_agent_did'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid receiver DID format',
                ], 400);
            }

            // Prevent self-transfer
            if ($did === $validated['to_agent_did']) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Cannot transfer to self',
                ], 400);
            }

            // Verify sender agent exists
            $senderAgent = $this->agentRegistryService->getAgentByDID($did);
            if (! $senderAgent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Sender agent not found',
                ], 404);
            }

            // Verify receiver agent exists
            $receiverAgent = $this->agentRegistryService->getAgentByDID($validated['to_agent_did']);
            if (! $receiverAgent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Receiver agent not found',
                ], 404);
            }

            $transactionId = 'txn-' . Str::uuid()->toString();

            // Create transaction aggregate using static method
            $aggregate = AgentTransactionAggregate::initiate(
                transactionId: $transactionId,
                fromAgentId: $senderAgent['agent_id'],
                toAgentId: $receiverAgent['agent_id'],
                amount: (float) $validated['amount'],
                currency: $validated['currency'],
                type: ($validated['escrow_required'] ?? false) ? 'escrow' : 'direct',
                metadata: array_merge($validated['metadata'] ?? [], [
                    'description'    => $validated['description'] ?? null,
                    'initiated_by'   => $request->user()?->id,
                    'from_agent_did' => $did,
                    'to_agent_did'   => $validated['to_agent_did'],
                ])
            );
            $aggregate->persist();

            // If escrow not required, validate immediately
            if (! ($validated['escrow_required'] ?? false)) {
                $aggregate->validate([
                    'validation_type'    => 'standard',
                    'compliance_checked' => true,
                ]);
                $aggregate->persist();
            }

            Log::info('Agent payment initiated', [
                'transaction_id' => $transactionId,
                'from'           => $did,
                'to'             => $validated['to_agent_did'],
                'amount'         => $validated['amount'],
                'currency'       => $validated['currency'],
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'transaction_id'  => $transactionId,
                    'from_agent_did'  => $did,
                    'to_agent_did'    => $validated['to_agent_did'],
                    'amount'          => (float) $validated['amount'],
                    'currency'        => $validated['currency'],
                    'status'          => $validated['escrow_required'] ? 'pending_escrow' : 'processing',
                    'escrow_required' => $validated['escrow_required'] ?? false,
                    'initiated_at'    => now()->toIso8601String(),
                ],
            ], 201);
        } catch (Exception $e) {
            Log::error('Payment initiation failed', [
                'from'  => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Payment initiation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{did}/payments/{transactionId}',
            operationId: 'getAgentPaymentStatus',
            tags: ['Agent Protocol - Payments'],
            summary: 'Get payment status',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'transactionId', in: 'path', required: true, description: 'Transaction ID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Payment status',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Payment not found'
    )]
    public function getPaymentStatus(string $did, string $transactionId): JsonResponse
    {
        try {
            // Validate DID
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid DID format',
                ], 400);
            }

            // Retrieve transaction aggregate
            $aggregate = AgentTransactionAggregate::retrieve($transactionId);

            // Check if transaction exists by checking if it has a transaction ID
            if (empty($aggregate->getTransactionId())) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Transaction not found',
                ], 404);
            }

            // Get metadata to check DIDs
            $metadata = $aggregate->getMetadata();
            $fromDid = $metadata['from_agent_did'] ?? null;
            $toDid = $metadata['to_agent_did'] ?? null;

            // Verify the DID is involved in this transaction
            if ($fromDid !== $did && $toDid !== $did) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Not authorized to view this transaction',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'transaction_id' => $aggregate->getTransactionId(),
                    'from_agent_id'  => $aggregate->getFromAgentId(),
                    'to_agent_id'    => $aggregate->getToAgentId(),
                    'from_agent_did' => $fromDid,
                    'to_agent_did'   => $toDid,
                    'amount'         => $aggregate->getAmount(),
                    'currency'       => $aggregate->getCurrency(),
                    'status'         => $aggregate->getStatus(),
                    'type'           => $aggregate->getType(),
                    'fees'           => $aggregate->getFees(),
                    'metadata'       => $metadata,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Get payment status failed', [
                'transaction_id' => $transactionId,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to get payment status: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Post(
            path: '/api/agent-protocol/agents/{did}/payments/{transactionId}/confirm',
            operationId: 'confirmAgentPayment',
            tags: ['Agent Protocol - Payments'],
            summary: 'Confirm a pending payment',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'transactionId', in: 'path', required: true, description: 'Transaction ID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Payment confirmed'
    )]
    #[OA\Response(
        response: 400,
        description: 'Cannot confirm payment'
    )]
    #[OA\Response(
        response: 404,
        description: 'Payment not found'
    )]
    public function confirmPayment(Request $request, string $did, string $transactionId): JsonResponse
    {
        try {
            // Validate DID
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid DID format',
                ], 400);
            }

            $aggregate = AgentTransactionAggregate::retrieve($transactionId);

            if (empty($aggregate->getTransactionId())) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Transaction not found',
                ], 404);
            }

            // Get metadata to check DIDs
            $metadata = $aggregate->getMetadata();
            $fromDid = $metadata['from_agent_did'] ?? null;

            // Only sender can confirm
            if ($fromDid !== $did) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Only sender can confirm payment',
                ], 403);
            }

            // Check if payment can be confirmed
            $status = $aggregate->getStatus();
            if (! in_array($status, ['initiated', 'validated', 'processing'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Payment cannot be confirmed in current state: ' . $status,
                ], 400);
            }

            // Complete the transaction
            $aggregate->complete('success', [
                'confirmed_by' => $request->user()?->id,
                'confirmed_at' => now()->toIso8601String(),
            ]);
            $aggregate->persist();

            Log::info('Agent payment confirmed', [
                'transaction_id' => $transactionId,
                'confirmed_by'   => $did,
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'transaction_id' => $transactionId,
                    'status'         => 'completed',
                    'confirmed_at'   => now()->toIso8601String(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Payment confirmation failed', [
                'transaction_id' => $transactionId,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Payment confirmation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Post(
            path: '/api/agent-protocol/agents/{did}/payments/{transactionId}/cancel',
            operationId: 'cancelAgentPayment',
            tags: ['Agent Protocol - Payments'],
            summary: 'Cancel a pending payment',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'transactionId', in: 'path', required: true, description: 'Transaction ID', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'reason', type: 'string', example: 'User requested cancellation'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Payment cancelled'
    )]
    #[OA\Response(
        response: 400,
        description: 'Cannot cancel payment'
    )]
    #[OA\Response(
        response: 404,
        description: 'Payment not found'
    )]
    public function cancelPayment(Request $request, string $did, string $transactionId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            // Validate DID
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid DID format',
                ], 400);
            }

            $aggregate = AgentTransactionAggregate::retrieve($transactionId);

            if (empty($aggregate->getTransactionId())) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Transaction not found',
                ], 404);
            }

            // Get metadata to check DIDs
            $metadata = $aggregate->getMetadata();
            $fromDid = $metadata['from_agent_did'] ?? null;

            // Only sender can cancel
            if ($fromDid !== $did) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Only sender can cancel payment',
                ], 403);
            }

            // Check if payment can be cancelled
            $status = $aggregate->getStatus();
            if (in_array($status, ['completed', 'failed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Payment cannot be cancelled in current state: ' . $status,
                ], 400);
            }

            // Fail the transaction with cancellation reason
            $aggregate->fail(
                $validated['reason'] ?? 'Cancelled by sender',
                [
                    'cancelled_by' => $request->user()?->id,
                    'cancelled_at' => now()->toIso8601String(),
                ]
            );
            $aggregate->persist();

            Log::info('Agent payment cancelled', [
                'transaction_id' => $transactionId,
                'cancelled_by'   => $did,
                'reason'         => $validated['reason'] ?? 'Cancelled by sender',
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'transaction_id' => $transactionId,
                    'status'         => 'cancelled',
                    'reason'         => $validated['reason'] ?? 'Cancelled by sender',
                    'cancelled_at'   => now()->toIso8601String(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Payment cancellation failed', [
                'transaction_id' => $transactionId,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Payment cancellation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{did}/payments',
            operationId: 'listAgentPayments',
            tags: ['Agent Protocol - Payments'],
            summary: 'List payments for an agent',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filter by status', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', description: 'Filter by type (sent/received)', schema: new OA\Schema(type: 'string', enum: ['sent', 'received', 'all'])),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Number of results', schema: new OA\Schema(type: 'integer', default: 20)),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'List of payments',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        ])
    )]
    public function listPayments(Request $request, string $did): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'type'   => ['nullable', 'string', Rule::in(['sent', 'received', 'all'])],
            'limit'  => 'nullable|integer|min:1|max:100',
        ]);

        try {
            // Validate DID
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid DID format',
                ], 400);
            }

            // In a real implementation, this would query the event store
            // For now, return an empty list structure
            $payments = $this->agentRegistryService->getAgentPayments(
                $did,
                $validated['status'] ?? null,
                $validated['type'] ?? 'all',
                $validated['limit'] ?? 20
            );

            return response()->json([
                'success' => true,
                'data'    => $payments,
                'meta'    => [
                    'agent_did' => $did,
                    'count'     => count($payments),
                    'filter'    => array_filter($validated),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('List payments failed', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to list payments: ' . $e->getMessage(),
            ], 500);
        }
    }
}
