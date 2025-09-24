<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol\Compliance;

use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Services\DigitalSignatureService;
use App\Domain\AgentProtocol\Services\EncryptionService;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * A2A (Agent-to-Agent) Protocol Validator Test Suite.
 *
 * Tests agent-to-agent communication protocol including:
 * - Message format validation
 * - Protocol version compatibility
 * - Acknowledgment mechanisms
 * - Encryption and signature verification
 */
class A2AProtocolValidatorTest extends TestCase
{
    use RefreshDatabase;

    private DigitalSignatureService $signatureService;

    private EncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signatureService = app(DigitalSignatureService::class);
        $this->encryptionService = app(EncryptionService::class);
    }

    /**
     * Test message format validation.
     */
    public function test_validates_a2a_message_format(): void
    {
        $sender = Agent::factory()->create();
        $recipient = Agent::factory()->create();

        // Valid message format
        $validMessage = [
            'header' => [
                'version'    => '2.0',
                'message_id' => 'msg_123',
                'timestamp'  => now()->toIso8601String(),
                'from'       => $sender->did,
                'to'         => $recipient->did,
            ],
            'body' => [
                'type'    => 'payment_request',
                'content' => [
                    'amount'   => 100.00,
                    'currency' => 'USD',
                ],
            ],
            'signature' => 'valid-signature',
        ];

        $response = $this->postJson('/api/a2a/validate', $validMessage);

        $response->assertStatus(200)
            ->assertJson([
                'valid'          => true,
                'format_version' => '2.0',
            ]);

        // Invalid message format (missing required fields)
        $invalidMessage = [
            'header' => [
                'from' => $sender->did,
            ],
            'body' => [
                'content' => 'test',
            ],
        ];

        $response = $this->postJson('/api/a2a/validate', $invalidMessage);

        $response->assertStatus(422)
            ->assertJson([
                'valid'  => false,
                'errors' => [
                    'header.version'    => ['required'],
                    'header.message_id' => ['required'],
                    'header.timestamp'  => ['required'],
                    'header.to'         => ['required'],
                    'body.type'         => ['required'],
                    'signature'         => ['required'],
                ],
            ]);
    }

    /**
     * Test protocol version compatibility.
     */
    public function test_protocol_version_compatibility(): void
    {
        $sender = Agent::factory()->create();
        $recipient = Agent::factory()->create();

        // Test with compatible versions
        $compatibleVersions = ['1.0', '1.1', '2.0'];

        foreach ($compatibleVersions as $version) {
            $message = $this->createTestMessage($sender, $recipient, $version);

            $response = $this->postJson('/api/a2a/messages', $message);

            $response->assertStatus(200)
                ->assertJson([
                    'accepted'           => true,
                    'version_compatible' => true,
                ]);
        }

        // Test with incompatible version
        $message = $this->createTestMessage($sender, $recipient, '0.5');

        $response = $this->postJson('/api/a2a/messages', $message);

        $response->assertStatus(400)
            ->assertJson([
                'accepted'           => false,
                'error'              => 'incompatible_version',
                'supported_versions' => ['1.0', '1.1', '2.0'],
            ]);
    }

    /**
     * Test acknowledgment mechanism.
     */
    public function test_acknowledgment_mechanism(): void
    {
        Event::fake();

        $sender = Agent::factory()->create();
        $recipient = Agent::factory()->create();

        $message = $this->createTestMessage($sender, $recipient);

        // Send message requiring acknowledgment
        $message['header']['require_ack'] = true;

        $response = $this->postJson('/api/a2a/messages', $message);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message_id',
                'ack_required',
                'ack_timeout',
            ]);

        $messageId = $response->json('message_id');

        // Send acknowledgment
        $ack = [
            'message_id' => $messageId,
            'status'     => 'received',
            'timestamp'  => now()->toIso8601String(),
            'signature'  => 'ack-signature',
        ];

        $response = $this->postJson('/api/a2a/acknowledge', $ack);

        $response->assertStatus(200)
            ->assertJson([
                'acknowledged' => true,
            ]);

        // Verify acknowledgment was recorded
        $this->assertDatabaseHas('message_acknowledgments', [
            'message_id' => $messageId,
            'status'     => 'received',
        ]);
    }

    /**
     * Test message encryption.
     */
    public function test_message_encryption(): void
    {
        $sender = Agent::factory()->create([
            'public_key' => $this->encryptionService->generateKeyPair()['public'],
        ]);

        $recipient = Agent::factory()->create([
            'public_key' => $this->encryptionService->generateKeyPair()['public'],
        ]);

        $plainMessage = [
            'type'    => 'confidential',
            'content' => 'Secret payment information',
        ];

        // Encrypt message
        $encryptedMessage = $this->encryptionService->encryptForAgent(
            $plainMessage,
            $recipient->public_key
        );

        $message = [
            'header' => [
                'version'    => '2.0',
                'message_id' => 'encrypted_msg_123',
                'timestamp'  => now()->toIso8601String(),
                'from'       => $sender->did,
                'to'         => $recipient->did,
                'encrypted'  => true,
            ],
            'body'      => $encryptedMessage,
            'signature' => 'encrypted-message-signature',
        ];

        $response = $this->postJson('/api/a2a/messages', $message);

        $response->assertStatus(200)
            ->assertJson([
                'accepted'  => true,
                'encrypted' => true,
            ]);

        // Verify recipient can decrypt
        $response = $this->actingAs($recipient, 'agent')
            ->getJson('/api/a2a/messages/encrypted_msg_123');

        $response->assertStatus(200)
            ->assertJson([
                'decrypted' => true,
                'content'   => $plainMessage,
            ]);
    }

    /**
     * Test signature verification.
     */
    public function test_signature_verification(): void
    {
        $sender = Agent::factory()->create();
        $recipient = Agent::factory()->create();

        $message = $this->createTestMessage($sender, $recipient);

        // Sign message
        $signature = $this->signatureService->signMessage(
            json_encode($message['header']) . json_encode($message['body']),
            $sender->agent_id
        );

        $message['signature'] = $signature;

        $response = $this->postJson('/api/a2a/messages', $message);

        $response->assertStatus(200)
            ->assertJson([
                'accepted'        => true,
                'signature_valid' => true,
            ]);

        // Test with invalid signature
        $message['signature'] = 'invalid-signature';

        $response = $this->postJson('/api/a2a/messages', $message);

        $response->assertStatus(401)
            ->assertJson([
                'accepted' => false,
                'error'    => 'invalid_signature',
            ]);
    }

    /**
     * Test message routing.
     */
    public function test_message_routing(): void
    {
        $agents = Agent::factory()->count(3)->create();

        // Direct routing
        $directMessage = $this->createTestMessage($agents[0], $agents[1]);

        $response = $this->postJson('/api/a2a/messages', $directMessage);

        $response->assertStatus(200)
            ->assertJson([
                'routing' => 'direct',
                'hops'    => 1,
            ]);

        // Multi-hop routing
        $multiHopMessage = $this->createTestMessage($agents[0], $agents[2]);
        $multiHopMessage['header']['route'] = [
            $agents[0]->did,
            $agents[1]->did,
            $agents[2]->did,
        ];

        $response = $this->postJson('/api/a2a/messages', $multiHopMessage);

        $response->assertStatus(200)
            ->assertJson([
                'routing' => 'multi-hop',
                'hops'    => 3,
            ]);
    }

    /**
     * Test message retry mechanism.
     */
    public function test_message_retry_mechanism(): void
    {
        $sender = Agent::factory()->create();
        $recipient = Agent::factory()->create([
            'status' => 'offline',
        ]);

        $message = $this->createTestMessage($sender, $recipient);
        $message['header']['retry_policy'] = [
            'max_retries'    => 3,
            'retry_interval' => 60,
        ];

        $response = $this->postJson('/api/a2a/messages', $message);

        $response->assertStatus(202)
            ->assertJson([
                'accepted'         => true,
                'queued_for_retry' => true,
                'retry_attempts'   => 0,
                'max_retries'      => 3,
            ]);

        // Verify message is queued for retry
        $this->assertDatabaseHas('message_queue', [
            'recipient_id' => $recipient->agent_id,
            'status'       => 'pending_retry',
            'max_retries'  => 3,
        ]);
    }

    /**
     * Test message expiration.
     */
    public function test_message_expiration(): void
    {
        $sender = Agent::factory()->create();
        $recipient = Agent::factory()->create();

        // Message with future expiration
        $futureMessage = $this->createTestMessage($sender, $recipient);
        $futureMessage['header']['expires_at'] = now()->addHour()->toIso8601String();

        $response = $this->postJson('/api/a2a/messages', $futureMessage);

        $response->assertStatus(200)
            ->assertJson([
                'accepted' => true,
                'expired'  => false,
            ]);

        // Expired message
        $expiredMessage = $this->createTestMessage($sender, $recipient);
        $expiredMessage['header']['expires_at'] = now()->subHour()->toIso8601String();

        $response = $this->postJson('/api/a2a/messages', $expiredMessage);

        $response->assertStatus(400)
            ->assertJson([
                'accepted' => false,
                'error'    => 'message_expired',
            ]);
    }

    /**
     * Test broadcast messages.
     */
    public function test_broadcast_messages(): void
    {
        $sender = Agent::factory()->create();
        $recipients = Agent::factory()->count(5)->create([
            'capabilities' => ['payment'],
        ]);

        $broadcastMessage = [
            'header' => [
                'version'    => '2.0',
                'message_id' => 'broadcast_123',
                'timestamp'  => now()->toIso8601String(),
                'from'       => $sender->did,
                'to'         => 'broadcast:capability:payment',
                'broadcast'  => true,
            ],
            'body' => [
                'type'    => 'announcement',
                'content' => [
                    'message' => 'New payment feature available',
                ],
            ],
            'signature' => 'broadcast-signature',
        ];

        $response = $this->postJson('/api/a2a/messages', $broadcastMessage);

        $response->assertStatus(200)
            ->assertJson([
                'accepted'         => true,
                'broadcast'        => true,
                'recipients_count' => 5,
            ]);

        // Verify all payment-capable agents received the message
        foreach ($recipients as $recipient) {
            $this->assertDatabaseHas('agent_messages', [
                'recipient_id' => $recipient->agent_id,
                'message_id'   => 'broadcast_123',
            ]);
        }
    }

    /**
     * Test message priority handling.
     */
    public function test_message_priority_handling(): void
    {
        $sender = Agent::factory()->create();
        $recipient = Agent::factory()->create();

        // High priority message
        $highPriorityMessage = $this->createTestMessage($sender, $recipient);
        $highPriorityMessage['header']['priority'] = 'high';

        $response = $this->postJson('/api/a2a/messages', $highPriorityMessage);

        $response->assertStatus(200)
            ->assertJson([
                'accepted'            => true,
                'priority_processing' => true,
            ]);

        // Normal priority message
        $normalMessage = $this->createTestMessage($sender, $recipient);
        $normalMessage['header']['priority'] = 'normal';

        $response = $this->postJson('/api/a2a/messages', $normalMessage);

        $response->assertStatus(200)
            ->assertJson([
                'accepted'            => true,
                'priority_processing' => false,
            ]);

        // Verify high priority message is processed first
        $messages = DB::table('message_queue')
            ->where('recipient_id', $recipient->agent_id)
            ->orderBy('processing_order')
            ->get();

        $this->assertEquals('high', $messages->first()->priority);
    }

    /**
     * Helper method to create a test message.
     */
    private function createTestMessage(Agent $sender, Agent $recipient, string $version = '2.0'): array
    {
        return [
            'header' => [
                'version'    => $version,
                'message_id' => 'test_msg_' . uniqid(),
                'timestamp'  => now()->toIso8601String(),
                'from'       => $sender->did,
                'to'         => $recipient->did,
            ],
            'body' => [
                'type'    => 'test',
                'content' => [
                    'message' => 'Test message content',
                ],
            ],
            'signature' => 'test-signature',
        ];
    }
}
