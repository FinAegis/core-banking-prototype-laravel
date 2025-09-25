<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol\Api;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Models\AgentMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected string $senderDid;

    protected string $receiverDid;

    protected string $senderAgentId;

    protected string $receiverAgentId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sender agent
        $this->senderAgentId = Str::uuid()->toString();
        $this->senderDid = 'did:example:msg-sender';
        $senderAggregate = AgentIdentityAggregate::register(
            agentId: $this->senderAgentId,
            did: $this->senderDid,
            name: 'Message Sender',
            type: 'assistant'
        );
        $senderAggregate->persist();

        Agent::create([
            'agent_id' => $this->senderAgentId,
            'did'      => $this->senderDid,
            'name'     => 'Message Sender',
            'type'     => 'assistant',
            'status'   => 'active',
        ]);

        // Create receiver agent
        $this->receiverAgentId = Str::uuid()->toString();
        $this->receiverDid = 'did:example:msg-receiver';
        $receiverAggregate = AgentIdentityAggregate::register(
            agentId: $this->receiverAgentId,
            did: $this->receiverDid,
            name: 'Message Receiver',
            type: 'service'
        );
        $receiverAggregate->persist();

        Agent::create([
            'agent_id' => $this->receiverAgentId,
            'did'      => $this->receiverDid,
            'name'     => 'Message Receiver',
            'type'     => 'service',
            'status'   => 'active',
        ]);
    }

    public function test_can_send_message_to_agent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/agents/{$this->senderDid}/messages", [
            'receiver_did'            => $this->receiverDid,
            'message_type'            => 'text',
            'payload'                 => 'Hello, this is a test message',
            'priority'                => 'normal',
            'requires_acknowledgment' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message_id',
                'status',
                'sender_did',
                'receiver_did',
                'created_at',
            ])
            ->assertJson([
                'status'       => 'queued',
                'sender_did'   => $this->senderDid,
                'receiver_did' => $this->receiverDid,
            ]);
    }

    public function test_can_send_json_message(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $jsonContent = [
            'action'     => 'process_payment',
            'parameters' => [
                'amount'   => 100.00,
                'currency' => 'USD',
            ],
        ];

        $response = $this->postJson("/api/agents/{$this->senderDid}/messages", [
            'receiver_did' => $this->receiverDid,
            'message_type' => 'json',
            'content'      => json_encode($jsonContent),
            'priority'     => 'high',
        ]);

        $response->assertStatus(201);
    }

    public function test_can_retrieve_agent_messages(): void
    {
        // Create some messages first
        AgentMessage::create([
            'message_id'              => Str::uuid()->toString(),
            'from_agent_id'           => $this->senderAgentId,
            'to_agent_id'             => $this->receiverAgentId,
            'message_type'            => 'text',
            'payload'                 => 'Message 1',
            'status'                  => 'delivered',
            'priority'                => 'normal',
            'requires_acknowledgment' => false,
        ]);

        AgentMessage::create([
            'message_id'              => Str::uuid()->toString(),
            'from_agent_id'           => $this->receiverAgentId,
            'to_agent_id'             => $this->senderAgentId,
            'message_type'            => 'text',
            'payload'                 => 'Message 2',
            'status'                  => 'queued',
            'priority'                => 'high',
            'requires_acknowledgment' => true,
        ]);

        $response = $this->getJson("/api/agents/{$this->senderDid}/messages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'messages' => [
                    '*' => [
                        'message_id',
                        'sender_agent_id',
                        'receiver_agent_id',
                        'message_type',
                        'content',
                        'status',
                        'priority',
                        'requires_acknowledgment',
                        'created_at',
                    ],
                ],
                'total',
            ]);

        $this->assertCount(2, $response->json('messages'));
    }

    public function test_can_filter_messages_by_status(): void
    {
        // Create messages with different statuses
        AgentMessage::create([
            'message_id'              => Str::uuid()->toString(),
            'from_agent_id'           => $this->senderAgentId,
            'to_agent_id'             => $this->receiverAgentId,
            'message_type'            => 'text',
            'payload'                 => 'Delivered message',
            'status'                  => 'delivered',
            'priority'                => 'normal',
            'requires_acknowledgment' => false,
        ]);

        AgentMessage::create([
            'message_id'              => Str::uuid()->toString(),
            'from_agent_id'           => $this->senderAgentId,
            'to_agent_id'             => $this->receiverAgentId,
            'message_type'            => 'text',
            'payload'                 => 'Queued message',
            'status'                  => 'queued',
            'priority'                => 'normal',
            'requires_acknowledgment' => false,
        ]);

        $response = $this->getJson("/api/agents/{$this->senderDid}/messages?status=delivered");

        $response->assertStatus(200);
        $messages = $response->json('messages');
        $this->assertCount(1, $messages);
        $this->assertEquals('delivered', $messages[0]['status']);
    }

    public function test_can_filter_messages_by_direction(): void
    {
        // Create sent and received messages
        AgentMessage::create([
            'message_id'              => Str::uuid()->toString(),
            'from_agent_id'           => $this->senderAgentId,
            'to_agent_id'             => $this->receiverAgentId,
            'message_type'            => 'text',
            'payload'                 => 'Sent message',
            'status'                  => 'delivered',
            'priority'                => 'normal',
            'requires_acknowledgment' => false,
        ]);

        AgentMessage::create([
            'message_id'              => Str::uuid()->toString(),
            'from_agent_id'           => $this->receiverAgentId,
            'to_agent_id'             => $this->senderAgentId,
            'message_type'            => 'text',
            'payload'                 => 'Received message',
            'status'                  => 'delivered',
            'priority'                => 'normal',
            'requires_acknowledgment' => false,
        ]);

        // Test sent messages
        $response = $this->getJson("/api/agents/{$this->senderDid}/messages?direction=sent");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('messages'));

        // Test received messages
        $response = $this->getJson("/api/agents/{$this->senderDid}/messages?direction=received");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('messages'));

        // Test both directions
        $response = $this->getJson("/api/agents/{$this->senderDid}/messages?direction=both");
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('messages'));
    }

    public function test_can_acknowledge_message(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Send a message that requires acknowledgment
        $sendResponse = $this->postJson("/api/agents/{$this->senderDid}/messages", [
            'receiver_did'            => $this->receiverDid,
            'message_type'            => 'text',
            'payload'                 => 'Please acknowledge',
            'requires_acknowledgment' => true,
        ]);

        $messageId = $sendResponse->json('message_id');

        // Acknowledge the message as receiver
        $response = $this->postJson("/api/agents/{$this->receiverDid}/messages/{$messageId}/ack", [
            'acknowledgment_message' => 'Message received and understood',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message_id' => $messageId,
                'status'     => 'acknowledged',
            ]);
    }

    public function test_validates_message_content(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/agents/{$this->senderDid}/messages", [
            'receiver_did' => $this->receiverDid,
            'message_type' => 'text',
            'content'      => '', // Empty content
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_validates_message_priority(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/agents/{$this->senderDid}/messages", [
            'receiver_did' => $this->receiverDid,
            'message_type' => 'text',
            'content'      => 'Test message',
            'priority'     => 'invalid', // Invalid priority
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_handles_nonexistent_receiver(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/agents/{$this->senderDid}/messages", [
            'receiver_did' => 'did:example:nonexistent',
            'message_type' => 'text',
            'content'      => 'Test message',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Receiver agent not found',
            ]);
    }

    public function test_only_receiver_can_acknowledge(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Send a message
        $sendResponse = $this->postJson("/api/agents/{$this->senderDid}/messages", [
            'receiver_did'            => $this->receiverDid,
            'message_type'            => 'text',
            'payload'                 => 'Test',
            'requires_acknowledgment' => true,
        ]);

        $messageId = $sendResponse->json('message_id');

        // Try to acknowledge as sender (should fail)
        $response = $this->postJson("/api/agents/{$this->senderDid}/messages/{$messageId}/ack");

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Only receiver can acknowledge message',
            ]);
    }

    public function test_message_with_expiration(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $expiresAt = now()->addHours(1)->toIso8601String();

        $response = $this->postJson("/api/agents/{$this->senderDid}/messages", [
            'receiver_did' => $this->receiverDid,
            'message_type' => 'text',
            'content'      => 'Time-sensitive message',
            'priority'     => 'urgent',
            'expires_at'   => $expiresAt,
        ]);

        $response->assertStatus(201);
    }

    public function test_message_with_metadata(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $metadata = [
            'correlation_id' => 'corr-123',
            'request_type'   => 'payment_request',
            'version'        => '1.0',
        ];

        $response = $this->postJson("/api/agents/{$this->senderDid}/messages", [
            'receiver_did' => $this->receiverDid,
            'message_type' => 'json',
            'content'      => json_encode(['action' => 'test']),
            'metadata'     => $metadata,
        ]);

        $response->assertStatus(201);
    }

    public function test_requires_authentication_for_sending_messages(): void
    {
        $response = $this->postJson("/api/agents/{$this->senderDid}/messages", [
            'receiver_did' => $this->receiverDid,
            'content'      => 'Test',
        ]);

        $response->assertStatus(401);
    }

    public function test_requires_authentication_for_acknowledgment(): void
    {
        $response = $this->postJson("/api/agents/{$this->receiverDid}/messages/test-id/ack");

        $response->assertStatus(401);
    }
}
