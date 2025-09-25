<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\A2AMessageAggregate;
use App\Domain\AgentProtocol\DataObjects\MessageDeliveryRequest;
use App\Domain\AgentProtocol\Models\AgentMessage;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Workflows\MessageDeliveryWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentProtocol\AcknowledgeMessageRequest;
use App\Http\Requests\AgentProtocol\SendMessageRequest;
use App\Http\Resources\AgentProtocol\MessageCollection;
use App\Http\Resources\AgentProtocol\MessageResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

class AgentMessagingController extends Controller
{
    public function __construct(
        private readonly AgentRegistryService $registryService
    ) {
    }

    /**
     * Send a message to another agent.
     *
     * @OA\Post(
     *     path="/api/agents/{did}/messages",
     *     operationId="sendAgentMessage",
     *     tags={"Agent Protocol - Messaging"},
     *     summary="Send message to agent",
     *     description="Send an A2A protocol message to another agent",
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
     *         @OA\JsonContent(ref="#/components/schemas/SendMessageRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Message sent successfully",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResource")
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Agent not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function sendMessage(SendMessageRequest $request, string $did): JsonResponse
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

            $messageId = Str::uuid()->toString();

            // Create message aggregate using the send method
            $messageAggregate = A2AMessageAggregate::send(
                messageId: $messageId,
                fromAgentId: $senderAgent->agent_id,
                toAgentId: $receiverAgent->agent_id,
                payload: [
                    'type'     => $request->message_type ?? 'text',
                    'content'  => $request->content,
                    'metadata' => $request->metadata ?? [],
                ],
                messageType: 'direct',
                priority: match ($request->priority ?? 'normal') {
                    'high'   => A2AMessageAggregate::PRIORITY_HIGH,
                    'urgent' => A2AMessageAggregate::PRIORITY_URGENT,
                    'low'    => A2AMessageAggregate::PRIORITY_LOW,
                    default  => A2AMessageAggregate::PRIORITY_NORMAL,
                },
                correlationId: null,
                replyTo: null,
                headers: [],
                ttl: $request->expires_at ? (int) (strtotime($request->expires_at) - time()) : 86400
            );

            $messageAggregate->persist();

            // Create message delivery request
            $deliveryRequest = new MessageDeliveryRequest(
                messageId: $messageId,
                fromAgentId: $senderAgent->agent_id,
                toAgentId: $receiverAgent->agent_id,
                messageType: $request->message_type ?? 'text',
                payload: [
                    'content'  => $request->content,
                    'metadata' => $request->metadata ?? [],
                ],
                headers: [],
                priority: match ($request->priority ?? 'normal') {
                    'high'   => 75,
                    'urgent' => 100,
                    'low'    => 25,
                    default  => 50,
                },
                correlationId: null,
                replyTo: null,
                requiresAcknowledgment: $request->requires_acknowledgment ?? false,
                acknowledgmentTimeout: $request->acknowledgment_timeout ?? null,
                queueName: null,
                enableCompensation: false,
                metadata: array_merge($request->metadata ?? [], [
                    'sender_did'   => $did,
                    'receiver_did' => $request->receiver_did,
                ])
            );

            // Start message delivery workflow
            $workflow = WorkflowStub::make(MessageDeliveryWorkflow::class);
            $workflow->start($deliveryRequest);

            // Also store in database for querying
            AgentMessage::create([
                'message_id'              => $messageId,
                'sender_agent_id'         => $senderAgent->agent_id,
                'receiver_agent_id'       => $receiverAgent->agent_id,
                'message_type'            => $request->message_type ?? 'text',
                'content'                 => $request->content,
                'priority'                => $request->priority ?? 'normal',
                'status'                  => 'queued',
                'requires_acknowledgment' => $request->requires_acknowledgment ?? false,
                'expires_at'              => $request->expires_at,
                'metadata'                => $request->metadata ?? [],
            ]);

            DB::commit();

            Log::info('Agent message sent', [
                'message_id'   => $messageId,
                'sender_did'   => $did,
                'receiver_did' => $request->receiver_did,
            ]);

            return response()->json([
                'message_id'   => $messageId,
                'status'       => 'queued',
                'sender_did'   => $did,
                'receiver_did' => $request->receiver_did,
                'created_at'   => now()->toIso8601String(),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to send message', [
                'error'      => $e->getMessage(),
                'sender_did' => $did,
            ]);

            return response()->json([
                'error'   => 'Failed to send message',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Retrieve messages for an agent.
     *
     * @OA\Get(
     *     path="/api/agents/{did}/messages",
     *     operationId="getAgentMessages",
     *     tags={"Agent Protocol - Messaging"},
     *     summary="Retrieve agent messages",
     *     description="Retrieve messages for an agent",
     *
     *     @OA\Parameter(
     *         name="did",
     *         in="path",
     *         description="Agent DID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by message status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"queued", "delivered", "acknowledged", "failed", "expired"})
     *     ),
     *     @OA\Parameter(
     *         name="direction",
     *         in="query",
     *         description="Filter by message direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"sent", "received", "both"})
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of messages to retrieve",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Messages retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/MessageCollection")
     *     ),
     *     @OA\Response(response=404, description="Agent not found")
     * )
     */
    public function getMessages(Request $request, string $did): JsonResponse
    {
        // Verify agent exists
        $agent = $this->registryService->getAgentByDID($did);
        if (! $agent) {
            return response()->json([
                'error' => 'Agent not found',
                'did'   => $did,
            ], Response::HTTP_NOT_FOUND);
        }

        $query = AgentMessage::query();

        // Filter by direction
        $direction = $request->query('direction', 'both');
        if ($direction === 'sent') {
            $query->where('sender_agent_id', $agent->agent_id);
        } elseif ($direction === 'received') {
            $query->where('receiver_agent_id', $agent->agent_id);
        } else {
            $query->where(function ($q) use ($agent) {
                $q->where('sender_agent_id', $agent->agent_id)
                  ->orWhere('receiver_agent_id', $agent->agent_id);
            });
        }

        // Filter by status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Limit results
        $limit = min((int) $request->query('limit', 20), 100);
        $messages = $query->orderBy('created_at', 'desc')->limit($limit)->get();

        return response()->json([
            'messages' => $messages->map(function ($message) {
                return [
                    'message_id'              => $message->message_id,
                    'sender_agent_id'         => $message->sender_agent_id,
                    'receiver_agent_id'       => $message->receiver_agent_id,
                    'message_type'            => $message->message_type,
                    'content'                 => $message->content,
                    'status'                  => $message->status,
                    'priority'                => $message->priority,
                    'requires_acknowledgment' => $message->requires_acknowledgment,
                    'acknowledged_at'         => $message->acknowledged_at,
                    'expires_at'              => $message->expires_at,
                    'created_at'              => $message->created_at->toIso8601String(),
                ];
            }),
            'total' => $messages->count(),
        ]);
    }

    /**
     * Acknowledge a message.
     *
     * @OA\Post(
     *     path="/api/agents/{did}/messages/{id}/ack",
     *     operationId="acknowledgeAgentMessage",
     *     tags={"Agent Protocol - Messaging"},
     *     summary="Acknowledge message",
     *     description="Acknowledge receipt of a message",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="did",
     *         in="path",
     *         description="Receiver agent DID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Message ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(ref="#/components/schemas/AcknowledgeMessageRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Message acknowledged successfully"
     *     ),
     *     @OA\Response(response=404, description="Message not found"),
     *     @OA\Response(response=409, description="Message already acknowledged")
     * )
     */
    public function acknowledgeMessage(AcknowledgeMessageRequest $request, string $did, string $id): JsonResponse
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

            // Load message aggregate
            $messageAggregate = A2AMessageAggregate::retrieve($id);

            // Verify agent is the receiver
            if ($messageAggregate->getToAgentId() !== $agent->agent_id) {
                return response()->json([
                    'error'      => 'Only receiver can acknowledge message',
                    'message_id' => $id,
                ], Response::HTTP_FORBIDDEN);
            }

            // Acknowledge the message
            $messageAggregate->acknowledge(
                acknowledgedBy: $agent->agent_id,
                acknowledgmentData: [
                    'message'   => $request->acknowledgment_message ?? null,
                    'timestamp' => now()->toIso8601String(),
                ]
            );

            $messageAggregate->persist();

            // Update database record
            AgentMessage::where('message_id', $id)->update([
                'status'          => 'acknowledged',
                'acknowledged_at' => now(),
            ]);

            DB::commit();

            Log::info('Message acknowledged', [
                'message_id'      => $id,
                'acknowledged_by' => $did,
            ]);

            return response()->json([
                'message_id'      => $id,
                'status'          => 'acknowledged',
                'acknowledged_at' => now()->toIso8601String(),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to acknowledge message', [
                'error'      => $e->getMessage(),
                'message_id' => $id,
            ]);

            return response()->json([
                'error'   => 'Failed to acknowledge message',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
