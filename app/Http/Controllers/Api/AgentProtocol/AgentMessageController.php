<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\A2AMessageAggregate;
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
    name: 'Agent Protocol - Messaging',
    description: 'Agent-to-Agent (A2A) messaging endpoints'
)]
class AgentMessageController extends Controller
{
    public function __construct(
        private readonly DIDService $didService,
        private readonly AgentRegistryService $agentRegistryService
    ) {
    }

        #[OA\Post(
            path: '/api/agent-protocol/agents/{did}/messages',
            operationId: 'sendAgentMessage',
            tags: ['Agent Protocol - Messaging'],
            summary: 'Send a message to another agent',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Sender agent DID', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['to_agent_did', 'message_type', 'payload'], properties: [
            new OA\Property(property: 'to_agent_did', type: 'string', example: 'did:finaegis:agent:receiver456'),
            new OA\Property(property: 'message_type', type: 'string', enum: ['direct', 'broadcast', 'protocol', 'transaction', 'notification'], example: 'direct'),
            new OA\Property(property: 'payload', type: 'object', example: ['action' => 'quote_request', 'data' => []]),
            new OA\Property(property: 'priority', type: 'string', enum: ['low', 'normal', 'high', 'critical'], example: 'normal'),
            new OA\Property(property: 'requires_acknowledgment', type: 'boolean', example: true),
            new OA\Property(property: 'correlation_id', type: 'string', nullable: true),
            new OA\Property(property: 'reply_to', type: 'string', nullable: true),
            new OA\Property(property: 'headers', type: 'object'),
            ]))
        )]
    #[OA\Response(
        response: 201,
        description: 'Message sent',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'message_id', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'sent_at', type: 'string', format: 'date-time'),
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
    public function send(Request $request, string $did): JsonResponse
    {
        $validated = $request->validate([
            'to_agent_did'            => 'required|string',
            'message_type'            => ['required', 'string', Rule::in(['direct', 'broadcast', 'protocol', 'transaction', 'notification'])],
            'payload'                 => 'required|array',
            'priority'                => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'critical'])],
            'requires_acknowledgment' => 'nullable|boolean',
            'correlation_id'          => 'nullable|string',
            'reply_to'                => 'nullable|string',
            'headers'                 => 'nullable|array',
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

            // Verify sender exists
            $sender = $this->agentRegistryService->getAgentByDID($did);
            if (! $sender) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Sender agent not found',
                ], 404);
            }

            // Verify receiver exists
            $receiver = $this->agentRegistryService->getAgentByDID($validated['to_agent_did']);
            if (! $receiver) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Receiver agent not found',
                ], 404);
            }

            $messageId = 'msg-' . Str::uuid()->toString();

            // Map priority string to numeric value
            $priorityValue = match ($validated['priority'] ?? 'normal') {
                'low'      => 0,
                'normal'   => 50,
                'high'     => 75,
                'critical' => 100,
                default    => 50,
            };

            // Create message aggregate using static send method
            $aggregate = A2AMessageAggregate::send(
                messageId: $messageId,
                fromAgentId: $sender['agent_id'],
                toAgentId: $receiver['agent_id'],
                payload: $validated['payload'],
                messageType: $validated['message_type'],
                priority: $priorityValue,
                correlationId: $validated['correlation_id'] ?? null,
                replyTo: $validated['reply_to'] ?? null,
                headers: $validated['headers'] ?? [],
                metadata: [
                    'from_agent_did' => $did,
                    'to_agent_did'   => $validated['to_agent_did'],
                    'sent_by_user'   => $request->user()?->id,
                ]
            );

            // Set acknowledgment requirement
            if ($validated['requires_acknowledgment'] ?? false) {
                $aggregate->setRequiresAcknowledgment(true);
            }

            // Queue the message for delivery
            $aggregate->queue('default');

            $aggregate->persist();

            Log::info('Agent message sent', [
                'message_id' => $messageId,
                'from'       => $did,
                'to'         => $validated['to_agent_did'],
                'type'       => $validated['message_type'],
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'message_id'              => $messageId,
                    'from_agent_did'          => $did,
                    'to_agent_did'            => $validated['to_agent_did'],
                    'message_type'            => $validated['message_type'],
                    'priority'                => $validated['priority'] ?? 'normal',
                    'status'                  => 'sent',
                    'requires_acknowledgment' => $validated['requires_acknowledgment'] ?? false,
                    'correlation_id'          => $validated['correlation_id'] ?? null,
                    'sent_at'                 => now()->toIso8601String(),
                ],
            ], 201);
        } catch (Exception $e) {
            Log::error('Send message failed', [
                'from'  => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to send message: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{did}/messages',
            operationId: 'getAgentMessages',
            tags: ['Agent Protocol - Messaging'],
            summary: 'Get messages for an agent',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', description: 'Filter by inbox/outbox', schema: new OA\Schema(type: 'string', enum: ['inbox', 'outbox', 'all'])),
            new OA\Parameter(name: 'status', in: 'query', description: 'Filter by status', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'unacknowledged_only', in: 'query', description: 'Only show unacknowledged messages', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Number of results', schema: new OA\Schema(type: 'integer', default: 20)),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'List of messages',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        ])
    )]
    public function list(Request $request, string $did): JsonResponse
    {
        $validated = $request->validate([
            'type'                => ['nullable', 'string', Rule::in(['inbox', 'outbox', 'all'])],
            'status'              => 'nullable|string',
            'unacknowledged_only' => 'nullable|boolean',
            'limit'               => 'nullable|integer|min:1|max:100',
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
            $messages = $this->agentRegistryService->getAgentMessages(
                $did,
                $validated['type'] ?? 'all',
                $validated['status'] ?? null,
                (bool) ($validated['unacknowledged_only'] ?? false),
                (int) ($validated['limit'] ?? 20)
            );

            return response()->json([
                'success' => true,
                'data'    => $messages,
                'meta'    => [
                    'agent_did' => $did,
                    'count'     => count($messages),
                    'filter'    => array_filter($validated),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('List messages failed', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to list messages: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Post(
            path: '/api/agent-protocol/agents/{did}/messages/{messageId}/ack',
            operationId: 'acknowledgeAgentMessage',
            tags: ['Agent Protocol - Messaging'],
            summary: 'Acknowledge receipt of a message',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Receiver agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'messageId', in: 'path', required: true, description: 'Message ID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Message acknowledged',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'message_id', type: 'string'),
        new OA\Property(property: 'acknowledged_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Cannot acknowledge message'
    )]
    #[OA\Response(
        response: 404,
        description: 'Message not found'
    )]
    public function acknowledge(Request $request, string $did, string $messageId): JsonResponse
    {
        try {
            // Validate DID
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid DID format',
                ], 400);
            }

            $aggregate = A2AMessageAggregate::retrieve($messageId);

            if (empty($aggregate->getMessageId())) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Message not found',
                ], 404);
            }

            // Get metadata to check DIDs
            $metadata = $aggregate->getMetadata();
            $toDid = $metadata['to_agent_did'] ?? null;

            // Verify the acknowledger is the intended recipient
            if ($toDid !== $did) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Only the intended recipient can acknowledge this message',
                ], 403);
            }

            // Check if already acknowledged
            if ($aggregate->getStatus() === 'acknowledged') {
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'message_id'           => $messageId,
                        'already_acknowledged' => true,
                        'acknowledged_at'      => $aggregate->getAcknowledgedAt()?->toIso8601String() ?? now()->toIso8601String(),
                    ],
                ]);
            }

            // Mark as delivered if not already (status is 'sent' or 'queued')
            if (in_array($aggregate->getStatus(), ['sent', 'queued'])) {
                $aggregate->deliver('api', [
                    'delivered_at' => now()->toIso8601String(),
                ]);
            }

            // Acknowledge the message
            $aggregate->acknowledge(
                $did,
                'ack-' . Str::uuid()->toString(),
                [
                    'acknowledged_by_user' => $request->user()?->id,
                ]
            );
            $aggregate->persist();

            Log::info('Message acknowledged', [
                'message_id'      => $messageId,
                'acknowledged_by' => $did,
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'message_id'      => $messageId,
                    'status'          => 'acknowledged',
                    'acknowledged_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Acknowledge message failed', [
                'message_id' => $messageId,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to acknowledge message: ' . $e->getMessage(),
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{did}/messages/{messageId}',
            operationId: 'getAgentMessage',
            tags: ['Agent Protocol - Messaging'],
            summary: 'Get message details',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID (must be sender or receiver)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'messageId', in: 'path', required: true, description: 'Message ID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Message details',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Message not found'
    )]
    public function show(string $did, string $messageId): JsonResponse
    {
        try {
            // Validate DID
            if (! $this->didService->validateDID($did)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid DID format',
                ], 400);
            }

            $aggregate = A2AMessageAggregate::retrieve($messageId);

            if (empty($aggregate->getMessageId())) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Message not found',
                ], 404);
            }

            // Get metadata to check DIDs
            $metadata = $aggregate->getMetadata();
            $fromDid = $metadata['from_agent_did'] ?? null;
            $toDid = $metadata['to_agent_did'] ?? null;

            // Verify the requester is either sender or receiver
            if ($fromDid !== $did && $toDid !== $did) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Not authorized to view this message',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'message_id'              => $aggregate->getMessageId(),
                    'from_agent_id'           => $aggregate->getFromAgentId(),
                    'to_agent_id'             => $aggregate->getToAgentId(),
                    'from_agent_did'          => $fromDid,
                    'to_agent_did'            => $toDid,
                    'message_type'            => $aggregate->getMessageType(),
                    'payload'                 => $aggregate->getPayload(),
                    'priority'                => $aggregate->getPriority(),
                    'status'                  => $aggregate->getStatus(),
                    'requires_acknowledgment' => $aggregate->requiresAcknowledgment(),
                    'metadata'                => $metadata,
                    'delivered_at'            => $aggregate->getDeliveredAt()?->toIso8601String(),
                    'acknowledged_at'         => $aggregate->getAcknowledgedAt()?->toIso8601String(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Get message failed', [
                'message_id' => $messageId,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to get message: ' . $e->getMessage(),
            ], 500);
        }
    }
}
