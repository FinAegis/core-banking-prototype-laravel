<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API tests for Agent-to-Agent (A2A) messaging endpoints.
 */
class AgentMessagingApiTest extends TestCase
{
    private Agent $senderAgent;

    private Agent $receiverAgent;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        $this->user = User::factory()->create([
            'kyc_status' => 'approved',
        ]);

        // Create sender agent with messaging capability (32 hex chars for DID)
        $this->senderAgent = Agent::factory()->create([
            'did'          => 'did:finaegis:agent:' . bin2hex(random_bytes(16)),
            'status'       => 'active',
            'capabilities' => ['payments', 'messaging', 'escrow'],
        ]);

        // Create receiver agent with messaging capability (32 hex chars for DID)
        $this->receiverAgent = Agent::factory()->create([
            'did'          => 'did:finaegis:agent:' . bin2hex(random_bytes(16)),
            'status'       => 'active',
            'capabilities' => ['payments', 'messaging'],
        ]);
    }

    public function test_send_message_requires_authentication(): void
    {
        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
            [
                'to_agent_did' => $this->receiverAgent->did,
                'message_type' => 'direct',
                'payload'      => ['action' => 'test'],
            ]
        );

        $response->assertStatus(401);
    }

    public function test_send_message_with_valid_data(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
            [
                'to_agent_did'            => $this->receiverAgent->did,
                'message_type'            => 'direct',
                'payload'                 => ['action' => 'quote_request', 'data' => ['amount' => 100]],
                'priority'                => 'normal',
                'requires_acknowledgment' => true,
            ]
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'message_id',
                    'from_agent_did',
                    'to_agent_did',
                    'message_type',
                    'priority',
                    'status',
                    'requires_acknowledgment',
                    'sent_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data'    => [
                    'from_agent_did'          => $this->senderAgent->did,
                    'to_agent_did'            => $this->receiverAgent->did,
                    'message_type'            => 'direct',
                    'priority'                => 'normal',
                    'status'                  => 'sent',
                    'requires_acknowledgment' => true,
                ],
            ]);

        // Verify message ID is in expected format
        $data = $response->json('data');
        $this->assertStringStartsWith('msg-', $data['message_id']);
    }

    public function test_send_message_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
            []
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_agent_did', 'message_type', 'payload']);
    }

    public function test_send_message_validates_message_type(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
            [
                'to_agent_did' => $this->receiverAgent->did,
                'message_type' => 'invalid_type',
                'payload'      => ['action' => 'test'],
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message_type']);
    }

    public function test_send_message_validates_priority(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
            [
                'to_agent_did' => $this->receiverAgent->did,
                'message_type' => 'direct',
                'payload'      => ['action' => 'test'],
                'priority'     => 'invalid_priority',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_send_message_rejects_invalid_sender_did(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->postJson(
            '/api/agent-protocol/agents/invalid-did/messages',
            [
                'to_agent_did' => $this->receiverAgent->did,
                'message_type' => 'direct',
                'payload'      => ['action' => 'test'],
            ]
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid sender DID format',
            ]);
    }

    public function test_send_message_rejects_invalid_receiver_did(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
            [
                'to_agent_did' => 'invalid-receiver-did',
                'message_type' => 'direct',
                'payload'      => ['action' => 'test'],
            ]
        );

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid receiver DID format',
            ]);
    }

    public function test_send_message_rejects_nonexistent_sender(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $nonExistentDid = 'did:finaegis:agent:' . str_repeat('0', 32);

        $response = $this->postJson(
            "/api/agent-protocol/agents/{$nonExistentDid}/messages",
            [
                'to_agent_did' => $this->receiverAgent->did,
                'message_type' => 'direct',
                'payload'      => ['action' => 'test'],
            ]
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error'   => 'Sender agent not found',
            ]);
    }

    public function test_send_message_rejects_nonexistent_receiver(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $nonExistentDid = 'did:finaegis:agent:' . str_repeat('1', 32);

        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
            [
                'to_agent_did' => $nonExistentDid,
                'message_type' => 'direct',
                'payload'      => ['action' => 'test'],
            ]
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error'   => 'Receiver agent not found',
            ]);
    }

    public function test_send_message_with_all_message_types(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $messageTypes = ['direct', 'broadcast', 'protocol', 'transaction', 'notification'];

        foreach ($messageTypes as $type) {
            $response = $this->postJson(
                "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
                [
                    'to_agent_did' => $this->receiverAgent->did,
                    'message_type' => $type,
                    'payload'      => ['action' => 'test', 'type' => $type],
                ]
            );

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'data'    => [
                        'message_type' => $type,
                    ],
                ]);
        }
    }

    public function test_send_message_with_all_priority_levels(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $priorities = ['low', 'normal', 'high', 'critical'];

        foreach ($priorities as $priority) {
            $response = $this->postJson(
                "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
                [
                    'to_agent_did' => $this->receiverAgent->did,
                    'message_type' => 'direct',
                    'payload'      => ['action' => 'test'],
                    'priority'     => $priority,
                ]
            );

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'data'    => [
                        'priority' => $priority,
                    ],
                ]);
        }
    }

    public function test_send_message_with_correlation_id(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $correlationId = 'corr-' . Str::uuid()->toString();

        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
            [
                'to_agent_did'   => $this->receiverAgent->did,
                'message_type'   => 'direct',
                'payload'        => ['action' => 'test'],
                'correlation_id' => $correlationId,
            ]
        );

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'correlation_id' => $correlationId,
                ],
            ]);
    }

    public function test_list_messages_requires_authentication(): void
    {
        $response = $this->getJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages"
        );

        $response->assertStatus(401);
    }

    public function test_list_messages_returns_empty_for_new_agent(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->getJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages"
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['agent_did', 'count'],
            ])
            ->assertJson([
                'success' => true,
                'meta'    => [
                    'agent_did' => $this->senderAgent->did,
                ],
            ]);
    }

    public function test_list_messages_with_filters(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->getJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages?type=inbox&limit=10"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_list_messages_validates_type_filter(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->getJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages?type=invalid_type"
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_list_messages_rejects_invalid_did(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->getJson('/api/agent-protocol/agents/invalid-did/messages');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid DID format',
            ]);
    }

    public function test_show_message_requires_authentication(): void
    {
        $response = $this->getJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages/msg-test"
        );

        $response->assertStatus(401);
    }

    public function test_show_message_returns_not_found_for_invalid_id(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->getJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages/msg-nonexistent"
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error'   => 'Message not found',
            ]);
    }

    public function test_acknowledge_message_requires_authentication(): void
    {
        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->receiverAgent->did}/messages/msg-test/ack"
        );

        $response->assertStatus(401);
    }

    public function test_acknowledge_message_rejects_invalid_did(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->postJson('/api/agent-protocol/agents/invalid-did/messages/msg-test/ack');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid DID format',
            ]);
    }

    public function test_acknowledge_message_returns_not_found(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        $response = $this->postJson(
            "/api/agent-protocol/agents/{$this->receiverAgent->did}/messages/msg-nonexistent/ack"
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error'   => 'Message not found',
            ]);
    }

    public function test_full_message_flow_send_and_acknowledge(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        // Step 1: Send a message
        $sendResponse = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
            [
                'to_agent_did'            => $this->receiverAgent->did,
                'message_type'            => 'direct',
                'payload'                 => ['action' => 'quote_request', 'amount' => 500],
                'priority'                => 'high',
                'requires_acknowledgment' => true,
            ]
        );

        $sendResponse->assertStatus(201);
        $messageId = $sendResponse->json('data.message_id');

        // Step 2: Receiver views the message
        $showResponse = $this->getJson(
            "/api/agent-protocol/agents/{$this->receiverAgent->did}/messages/{$messageId}"
        );

        $showResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'message_id'              => $messageId,
                    'from_agent_did'          => $this->senderAgent->did,
                    'to_agent_did'            => $this->receiverAgent->did,
                    'message_type'            => 'direct',
                    'requires_acknowledgment' => true,
                ],
            ]);

        // Step 3: Receiver acknowledges the message
        $ackResponse = $this->postJson(
            "/api/agent-protocol/agents/{$this->receiverAgent->did}/messages/{$messageId}/ack"
        );

        $ackResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'message_id' => $messageId,
                    'status'     => 'acknowledged',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => ['message_id', 'status', 'acknowledged_at'],
            ]);
    }

    public function test_acknowledge_message_denied_for_non_recipient(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        // Send a message
        $sendResponse = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
            [
                'to_agent_did' => $this->receiverAgent->did,
                'message_type' => 'direct',
                'payload'      => ['action' => 'test'],
            ]
        );

        $sendResponse->assertStatus(201);
        $messageId = $sendResponse->json('data.message_id');

        // Try to acknowledge as sender (not the recipient)
        $ackResponse = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages/{$messageId}/ack"
        );

        $ackResponse->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error'   => 'Only the intended recipient can acknowledge this message',
            ]);
    }

    public function test_show_message_denied_for_unrelated_agent(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->withoutMiddleware([
            \App\Http\Middleware\AuthenticateAgentDID::class,
            \App\Http\Middleware\CheckAgentCapability::class,
            \App\Http\Middleware\CheckAgentScope::class,
        ]);

        // Create a third agent (32 hex chars for DID)
        $thirdAgent = Agent::factory()->create([
            'did'          => 'did:finaegis:agent:' . bin2hex(random_bytes(16)),
            'status'       => 'active',
            'capabilities' => ['messaging'],
        ]);

        // Send a message between sender and receiver
        $sendResponse = $this->postJson(
            "/api/agent-protocol/agents/{$this->senderAgent->did}/messages",
            [
                'to_agent_did' => $this->receiverAgent->did,
                'message_type' => 'direct',
                'payload'      => ['action' => 'private_test'],
            ]
        );

        $sendResponse->assertStatus(201);
        $messageId = $sendResponse->json('data.message_id');

        // Try to view as third agent (not sender or receiver)
        $showResponse = $this->getJson(
            "/api/agent-protocol/agents/{$thirdAgent->did}/messages/{$messageId}"
        );

        $showResponse->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error'   => 'Not authorized to view this message',
            ]);
    }
}
