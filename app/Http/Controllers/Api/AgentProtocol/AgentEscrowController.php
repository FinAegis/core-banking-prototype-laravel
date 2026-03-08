<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\EscrowAggregate;
use App\Domain\AgentProtocol\DataObjects\EscrowRequest;
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
    name: 'Agent Protocol - Escrow',
    description: 'Escrow management for secure agent-to-agent transactions'
)]
class AgentEscrowController extends Controller
{
    public function __construct(
        private readonly DIDService $didService,
        private readonly AgentRegistryService $agentRegistryService
    ) {
    }

        #[OA\Post(
            path: '/api/agent-protocol/escrow',
            operationId: 'createEscrow',
            tags: ['Agent Protocol - Escrow'],
            summary: 'Create a new escrow',
            security: [['sanctum' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['buyer_did', 'seller_did', 'amount', 'currency'], properties: [
            new OA\Property(property: 'buyer_did', type: 'string', example: 'did:finaegis:agent:buyer123'),
            new OA\Property(property: 'seller_did', type: 'string', example: 'did:finaegis:agent:seller456'),
            new OA\Property(property: 'amount', type: 'number', format: 'float', example: 1000.00),
            new OA\Property(property: 'currency', type: 'string', example: 'USD'),
            new OA\Property(property: 'conditions', type: 'array', example: ['delivery_confirmed', 'quality_verified'], items: new OA\Items(type: 'string')),
            new OA\Property(property: 'release_conditions', type: 'array', example: ['delivery_confirmed'], items: new OA\Items(type: 'string')),
            new OA\Property(property: 'timeout_seconds', type: 'integer', example: 86400),
            new OA\Property(property: 'dispute_resolution_did', type: 'string', nullable: true),
            new OA\Property(property: 'metadata', type: 'object'),
            ]))
        )]
    #[OA\Response(
        response: 201,
        description: 'Escrow created',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'escrow_id', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'amount', type: 'number'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
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
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'buyer_did'              => 'required|string',
            'seller_did'             => 'required|string',
            'amount'                 => 'required|numeric|min:0.01',
            'currency'               => ['required', 'string', Rule::in(['USD', 'EUR', 'GBP', 'GCU'])],
            'conditions'             => 'nullable|array',
            'conditions.*'           => 'string',
            'release_conditions'     => 'nullable|array',
            'release_conditions.*'   => 'string',
            'timeout_seconds'        => 'nullable|integer|min:3600|max:2592000', // 1 hour to 30 days
            'dispute_resolution_did' => 'nullable|string',
            'metadata'               => 'nullable|array',
        ]);

        try {
            // Validate DIDs
            if (! $this->didService->validateDID($validated['buyer_did'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid buyer DID format',
                ], 400);
            }

            if (! $this->didService->validateDID($validated['seller_did'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid seller DID format',
                ], 400);
            }

            if ($validated['buyer_did'] === $validated['seller_did']) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Buyer and seller cannot be the same',
                ], 400);
            }

            // Verify agents exist
            $buyer = $this->agentRegistryService->getAgentByDID($validated['buyer_did']);
            if (! $buyer) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Buyer agent not found',
                ], 404);
            }

            $seller = $this->agentRegistryService->getAgentByDID($validated['seller_did']);
            if (! $seller) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Seller agent not found',
                ], 404);
            }

            // Create escrow request
            $escrowRequest = new EscrowRequest(
                buyerDid: $validated['buyer_did'],
                sellerDid: $validated['seller_did'],
                amount: (float) $validated['amount'],
                currency: $validated['currency'],
                conditions: array_fill_keys($validated['conditions'] ?? [], false),
                releaseConditions: $validated['release_conditions'] ?? [],
                disputeResolutionDid: $validated['dispute_resolution_did'] ?? null,
                timeoutSeconds: $validated['timeout_seconds'] ?? 86400,
                metadata: $validated['metadata'] ?? null
            );

            // Create escrow aggregate using static method
            $transactionId = 'txn-' . Str::uuid()->toString();
            $aggregate = EscrowAggregate::create(
                escrowId: $escrowRequest->escrowId,
                transactionId: $transactionId,
                senderAgentId: $buyer['agent_id'],    // Buyer sends funds
                receiverAgentId: $seller['agent_id'], // Seller receives funds
                amount: $escrowRequest->amount,
                currency: $escrowRequest->currency,
                conditions: $escrowRequest->conditions,
                expiresAt: $escrowRequest->getTimeoutAt()->toIso8601String(),
                metadata: array_merge($escrowRequest->metadata ?? [], [
                    'buyer_did'              => $validated['buyer_did'],
                    'seller_did'             => $validated['seller_did'],
                    'release_conditions'     => $escrowRequest->releaseConditions,
                    'dispute_resolution_did' => $escrowRequest->disputeResolutionDid,
                    'created_by'             => $request->user()?->id,
                ])
            );
            $aggregate->persist();

            Log::info('Escrow created', [
                'escrow_id'  => $escrowRequest->escrowId,
                'buyer_did'  => $validated['buyer_did'],
                'seller_did' => $validated['seller_did'],
                'amount'     => $validated['amount'],
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'escrow_id'  => $escrowRequest->escrowId,
                    'buyer_did'  => $validated['buyer_did'],
                    'seller_did' => $validated['seller_did'],
                    'amount'     => $escrowRequest->amount,
                    'currency'   => $escrowRequest->currency,
                    'status'     => 'created',
                    'conditions' => $escrowRequest->conditions,
                    'expires_at' => $escrowRequest->getTimeoutAt()->toIso8601String(),
                    'created_at' => $escrowRequest->createdAt->toIso8601String(),
                ],
            ], 201);
        } catch (Exception $e) {
            Log::error('Escrow creation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Escrow creation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/escrow/{escrowId}',
            operationId: 'getEscrow',
            tags: ['Agent Protocol - Escrow'],
            summary: 'Get escrow details',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'escrowId', in: 'path', required: true, description: 'Escrow ID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Escrow details',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Escrow not found'
    )]
    public function show(string $escrowId): JsonResponse
    {
        try {
            $aggregate = EscrowAggregate::retrieve($escrowId);

            if (empty($aggregate->getEscrowId())) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Escrow not found',
                ], 404);
            }

            $metadata = $aggregate->getMetadata();

            return response()->json([
                'success' => true,
                'data'    => [
                    'escrow_id'         => $aggregate->getEscrowId(),
                    'transaction_id'    => $aggregate->getTransactionId(),
                    'sender_agent_id'   => $aggregate->getSenderAgentId(),
                    'receiver_agent_id' => $aggregate->getReceiverAgentId(),
                    'buyer_did'         => $metadata['buyer_did'] ?? null,
                    'seller_did'        => $metadata['seller_did'] ?? null,
                    'amount'            => $aggregate->getAmount(),
                    'funded_amount'     => $aggregate->getFundedAmount(),
                    'currency'          => $aggregate->getCurrency(),
                    'status'            => $aggregate->getStatus(),
                    'conditions'        => $aggregate->getConditions(),
                    'expires_at'        => $aggregate->getExpiresAt(),
                    'released_at'       => $aggregate->getReleasedAt(),
                    'released_by'       => $aggregate->getReleasedBy(),
                    'is_disputed'       => $aggregate->isDisputed(),
                    'disputed_by'       => $aggregate->getDisputedBy(),
                    'dispute_reason'    => $aggregate->getDisputeReason(),
                    'metadata'          => $metadata,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Get escrow failed', [
                'escrow_id' => $escrowId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to get escrow: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Post(
            path: '/api/agent-protocol/escrow/{escrowId}/fund',
            operationId: 'fundEscrow',
            tags: ['Agent Protocol - Escrow'],
            summary: 'Fund an escrow (deposit funds)',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'escrowId', in: 'path', required: true, description: 'Escrow ID', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['funder_did'], properties: [
            new OA\Property(property: 'funder_did', type: 'string', example: 'did:finaegis:agent:buyer123'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Escrow funded'
    )]
    #[OA\Response(
        response: 400,
        description: 'Cannot fund escrow'
    )]
    #[OA\Response(
        response: 404,
        description: 'Escrow not found'
    )]
    public function fund(Request $request, string $escrowId): JsonResponse
    {
        $validated = $request->validate([
            'funder_did' => 'required|string',
        ]);

        try {
            $aggregate = EscrowAggregate::retrieve($escrowId);

            if (empty($aggregate->getEscrowId())) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Escrow not found',
                ], 404);
            }

            $status = $aggregate->getStatus();
            if (! in_array($status, ['created', 'partially_funded'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Escrow cannot be funded in current state: ' . $status,
                ], 400);
            }

            // Calculate remaining amount to fund
            $remainingAmount = $aggregate->getAmount() - $aggregate->getFundedAmount();

            // Deposit funds to escrow
            $aggregate->deposit(
                $remainingAmount,
                $validated['funder_did'],
                ['funded_at' => now()->toIso8601String()]
            );
            $aggregate->persist();

            Log::info('Escrow funded', [
                'escrow_id' => $escrowId,
                'funded_by' => $validated['funder_did'],
                'amount'    => $remainingAmount,
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'escrow_id'     => $escrowId,
                    'status'        => $aggregate->isFunded() ? 'funded' : 'partially_funded',
                    'funded_amount' => $aggregate->getFundedAmount(),
                    'funded_at'     => now()->toIso8601String(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Fund escrow failed', [
                'escrow_id' => $escrowId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to fund escrow: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Post(
            path: '/api/agent-protocol/escrow/{escrowId}/release',
            operationId: 'releaseEscrow',
            tags: ['Agent Protocol - Escrow'],
            summary: 'Release escrow funds to seller',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'escrowId', in: 'path', required: true, description: 'Escrow ID', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'releaser_did', type: 'string', example: 'did:finaegis:agent:buyer123'),
            new OA\Property(property: 'conditions_met', type: 'array', items: new OA\Items(type: 'string')),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Escrow released'
    )]
    #[OA\Response(
        response: 400,
        description: 'Cannot release escrow'
    )]
    #[OA\Response(
        response: 404,
        description: 'Escrow not found'
    )]
    public function release(Request $request, string $escrowId): JsonResponse
    {
        $validated = $request->validate([
            'releaser_did'     => 'nullable|string',
            'conditions_met'   => 'nullable|array',
            'conditions_met.*' => 'string',
        ]);

        try {
            $aggregate = EscrowAggregate::retrieve($escrowId);

            if (empty($aggregate->getEscrowId())) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Escrow not found',
                ], 404);
            }

            $status = $aggregate->getStatus();
            if (! in_array($status, ['funded', 'resolved'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Escrow cannot be released in current state: ' . $status,
                ], 400);
            }

            // Release funds
            $aggregate->release(
                $validated['releaser_did'] ?? 'system',
                'conditions_met',
                [
                    'conditions_met' => $validated['conditions_met'] ?? [],
                    'released_at'    => now()->toIso8601String(),
                ]
            );
            $aggregate->persist();

            Log::info('Escrow released', [
                'escrow_id'   => $escrowId,
                'released_by' => $validated['releaser_did'] ?? 'system',
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'escrow_id'   => $escrowId,
                    'status'      => 'released',
                    'released_at' => now()->toIso8601String(),
                    'amount'      => $aggregate->getAmount(),
                    'released_to' => $aggregate->getReceiverAgentId(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Release escrow failed', [
                'escrow_id' => $escrowId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to release escrow: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Post(
            path: '/api/agent-protocol/escrow/{escrowId}/dispute',
            operationId: 'raiseEscrowDispute',
            tags: ['Agent Protocol - Escrow'],
            summary: 'Raise a dispute on an escrow',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'escrowId', in: 'path', required: true, description: 'Escrow ID', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['disputer_did', 'reason'], properties: [
            new OA\Property(property: 'disputer_did', type: 'string', example: 'did:finaegis:agent:buyer123'),
            new OA\Property(property: 'reason', type: 'string', example: 'Service not delivered as agreed'),
            new OA\Property(property: 'evidence', type: 'array', items: new OA\Items(type: 'string')),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Dispute raised'
    )]
    #[OA\Response(
        response: 400,
        description: 'Cannot dispute escrow'
    )]
    #[OA\Response(
        response: 404,
        description: 'Escrow not found'
    )]
    public function dispute(Request $request, string $escrowId): JsonResponse
    {
        $validated = $request->validate([
            'disputer_did' => 'required|string',
            'reason'       => 'required|string|max:1000',
            'evidence'     => 'nullable|array',
            'evidence.*'   => 'string',
        ]);

        try {
            // Validate DID
            if (! $this->didService->validateDID($validated['disputer_did'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid disputer DID format',
                ], 400);
            }

            $aggregate = EscrowAggregate::retrieve($escrowId);

            if (empty($aggregate->getEscrowId())) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Escrow not found',
                ], 404);
            }

            $status = $aggregate->getStatus();
            if ($status !== 'funded') {
                return response()->json([
                    'success' => false,
                    'error'   => 'Escrow cannot be disputed in current state: ' . $status,
                ], 400);
            }

            // Raise dispute using the dispute() method
            $aggregate->dispute(
                $validated['disputer_did'],
                $validated['reason'],
                $validated['evidence'] ?? []
            );
            $aggregate->persist();

            $disputeId = 'dispute-' . Str::uuid()->toString(); // For response only

            Log::info('Escrow dispute raised', [
                'escrow_id'  => $escrowId,
                'dispute_id' => $disputeId,
                'raised_by'  => $validated['disputer_did'],
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'escrow_id'  => $escrowId,
                    'dispute_id' => $disputeId,
                    'status'     => 'disputed',
                    'raised_by'  => $validated['disputer_did'],
                    'reason'     => $validated['reason'],
                    'raised_at'  => now()->toIso8601String(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Raise dispute failed', [
                'escrow_id' => $escrowId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to raise dispute: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Post(
            path: '/api/agent-protocol/escrow/{escrowId}/resolve',
            operationId: 'resolveEscrowDispute',
            tags: ['Agent Protocol - Escrow'],
            summary: 'Resolve an escrow dispute',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'escrowId', in: 'path', required: true, description: 'Escrow ID', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['resolver_did', 'resolution_type'], properties: [
            new OA\Property(property: 'resolver_did', type: 'string', example: 'did:finaegis:agent:arbiter789'),
            new OA\Property(property: 'resolution_type', type: 'string', enum: ['release_to_receiver', 'return_to_sender', 'split'], example: 'release_to_receiver'),
            new OA\Property(property: 'split_percentage', type: 'number', example: 50, description: 'Required if resolution_type is split'),
            new OA\Property(property: 'reason', type: 'string', example: 'Evidence supports seller\'s claim'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Dispute resolved'
    )]
    #[OA\Response(
        response: 400,
        description: 'Cannot resolve dispute'
    )]
    #[OA\Response(
        response: 404,
        description: 'Escrow not found'
    )]
    public function resolveDispute(Request $request, string $escrowId): JsonResponse
    {
        $validated = $request->validate([
            'resolver_did'     => 'required|string',
            'resolution_type'  => ['required', 'string', Rule::in(['release_to_receiver', 'return_to_sender', 'split'])],
            'split_percentage' => 'required_if:resolution_type,split|numeric|min:0|max:100',
            'reason'           => 'nullable|string|max:1000',
        ]);

        try {
            // Validate resolver DID
            if (! $this->didService->validateDID($validated['resolver_did'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid resolver DID format',
                ], 400);
            }

            $aggregate = EscrowAggregate::retrieve($escrowId);

            if (empty($aggregate->getEscrowId())) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Escrow not found',
                ], 404);
            }

            $status = $aggregate->getStatus();
            if ($status !== 'disputed') {
                return response()->json([
                    'success' => false,
                    'error'   => 'Escrow is not in disputed state: ' . $status,
                ], 400);
            }

            // Build resolution allocation
            $resolutionAllocation = [];
            if ($validated['resolution_type'] === 'split') {
                $senderPercentage = (float) $validated['split_percentage'];
                $receiverPercentage = 100.0 - $senderPercentage;
                $resolutionAllocation = [
                    $aggregate->getSenderAgentId()   => ($senderPercentage / 100) * $aggregate->getFundedAmount(),
                    $aggregate->getReceiverAgentId() => ($receiverPercentage / 100) * $aggregate->getFundedAmount(),
                ];
            }

            // Build resolution details
            $resolutionDetails = [
                'reason'      => $validated['reason'] ?? null,
                'resolved_at' => now()->toIso8601String(),
            ];

            // Resolve dispute
            $aggregate->resolveDispute(
                $validated['resolver_did'],
                $validated['resolution_type'],
                $resolutionAllocation,
                $resolutionDetails
            );
            $aggregate->persist();

            Log::info('Escrow dispute resolved', [
                'escrow_id'       => $escrowId,
                'resolution_type' => $validated['resolution_type'],
                'resolved_by'     => $validated['resolver_did'],
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'escrow_id'       => $escrowId,
                    'status'          => 'resolved',
                    'resolution_type' => $validated['resolution_type'],
                    'resolved_by'     => $validated['resolver_did'],
                    'resolved_at'     => now()->toIso8601String(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Resolve dispute failed', [
                'escrow_id' => $escrowId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to resolve dispute: ' . $e->getMessage(),
            ], 500);
        }
    }
}
