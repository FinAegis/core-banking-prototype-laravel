<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Messaging;

use App\Domain\AgentProtocol\Enums\MessagePriority;
use App\Domain\AgentProtocol\Enums\MessageStatus;
use App\Domain\AgentProtocol\Enums\MessageType;
use App\Domain\AgentProtocol\Messaging\A2AMessageEnvelope;
use DateTimeImmutable;
use Tests\TestCase;

class A2AMessageEnvelopeTest extends TestCase
{
    public function test_creates_envelope_with_auto_generated_id(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: ['action' => 'test']
        );

        $this->assertStringStartsWith('msg_', $envelope->messageId);
        $this->assertEquals('did:finaegis:key:sender123', $envelope->senderDid);
        $this->assertEquals('did:finaegis:key:recipient456', $envelope->recipientDid);
        $this->assertEquals(MessageType::REQUEST, $envelope->messageType);
        $this->assertEquals(['action' => 'test'], $envelope->payload);
        $this->assertEquals(MessagePriority::NORMAL, $envelope->priority);
        $this->assertEquals('1.0', $envelope->protocolVersion);
    }

    public function test_creates_envelope_with_custom_priority(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::PAYMENT_REQUEST,
            payload: ['amount' => 100],
            priority: MessagePriority::HIGH
        );

        $this->assertEquals(MessagePriority::HIGH, $envelope->priority);
    }

    public function test_creates_envelope_with_ttl(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::HEARTBEAT,
            payload: [],
            ttlSeconds: 60
        );

        $this->assertEquals(60, $envelope->ttlSeconds);
        $this->assertNotNull($envelope->expiresAt);
    }

    public function test_checks_message_expiration(): void
    {
        // Non-expired message
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: [],
            ttlSeconds: 3600
        );

        $this->assertFalse($envelope->isExpired());

        // Expired message (manually create with past expiration)
        $expiredEnvelope = new A2AMessageEnvelope(
            messageId: 'msg_expired',
            protocolVersion: '1.0',
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            priority: MessagePriority::NORMAL,
            payload: [],
            expiresAt: new DateTimeImmutable('-1 hour')
        );

        $this->assertTrue($expiredEnvelope->isExpired());
    }

    public function test_creates_response_envelope(): void
    {
        $original = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: ['query' => 'test'],
            correlationId: 'correlation_123'
        );

        $response = $original->createResponse(
            payload: ['result' => 'success'],
            responseType: MessageType::RESPONSE
        );

        // Response should swap sender/recipient
        $this->assertEquals('did:finaegis:key:recipient456', $response->senderDid);
        $this->assertEquals('did:finaegis:key:sender123', $response->recipientDid);

        // Response should reference original message
        $this->assertEquals($original->messageId, $response->correlationId);
        $this->assertEquals($original->messageId, $response->inReplyTo);
        $this->assertEquals($original->conversationId, $response->conversationId);

        // Response should have own ID
        $this->assertNotEquals($original->messageId, $response->messageId);
    }

    public function test_creates_acknowledgment(): void
    {
        $original = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::PAYMENT_REQUEST,
            payload: ['amount' => 100]
        );

        $ack = $original->createAcknowledgment(success: true);

        $this->assertEquals(MessageType::ACKNOWLEDGMENT, $ack->messageType);
        $this->assertEquals(MessagePriority::HIGH, $ack->priority);
        $this->assertEquals($original->messageId, $ack->correlationId);
        $this->assertTrue($ack->payload['acknowledged']);
        $this->assertEquals($original->messageId, $ack->payload['originalMessageId']);
        $this->assertFalse($ack->requiresAcknowledgment);
    }

    public function test_creates_failed_acknowledgment(): void
    {
        $original = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: []
        );

        $ack = $original->createAcknowledgment(success: false, errorMessage: 'Processing failed');

        $this->assertFalse($ack->payload['acknowledged']);
        $this->assertEquals('Processing failed', $ack->payload['error']);
    }

    public function test_requires_response_based_on_message_type(): void
    {
        $requestEnvelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: []
        );

        $this->assertTrue($requestEnvelope->requiresResponse());

        $eventEnvelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::EVENT,
            payload: []
        );

        $this->assertFalse($eventEnvelope->requiresResponse());
    }

    public function test_gets_queue_name_based_on_priority(): void
    {
        $criticalEnvelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: [],
            priority: MessagePriority::CRITICAL
        );

        $this->assertEquals('agent-messages-critical', $criticalEnvelope->getQueueName());

        $normalEnvelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: [],
            priority: MessagePriority::NORMAL
        );

        $this->assertEquals('agent-messages', $normalEnvelope->getQueueName());
    }

    public function test_updates_status(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: []
        );

        $this->assertEquals(MessageStatus::PENDING, $envelope->status);

        $updatedEnvelope = $envelope->withStatus(MessageStatus::DELIVERED);

        // Original should be unchanged (immutable)
        $this->assertEquals(MessageStatus::PENDING, $envelope->status);

        // New envelope should have updated status
        $this->assertEquals(MessageStatus::DELIVERED, $updatedEnvelope->status);

        // Other properties should be preserved
        $this->assertEquals($envelope->messageId, $updatedEnvelope->messageId);
        $this->assertEquals($envelope->senderDid, $updatedEnvelope->senderDid);
    }

    public function test_adds_signature(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: []
        );

        $this->assertNull($envelope->signature);

        $signedEnvelope = $envelope->withSignature('test_signature_value');

        // Original should be unchanged
        $this->assertNull($envelope->signature);

        // New envelope should have signature
        $this->assertEquals('test_signature_value', $signedEnvelope->signature);
    }

    public function test_converts_to_array_and_back(): void
    {
        $original = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::PAYMENT_REQUEST,
            payload: ['amount' => 100, 'currency' => 'USD'],
            priority: MessagePriority::HIGH,
            headers: ['X-Custom' => 'value'],
            correlationId: 'corr_123',
            ttlSeconds: 3600,
            metadata: ['source' => 'test']
        );

        $array = $original->toArray();
        $restored = A2AMessageEnvelope::fromArray($array);

        $this->assertEquals($original->messageId, $restored->messageId);
        $this->assertEquals($original->senderDid, $restored->senderDid);
        $this->assertEquals($original->recipientDid, $restored->recipientDid);
        $this->assertEquals($original->messageType, $restored->messageType);
        $this->assertEquals($original->priority, $restored->priority);
        $this->assertEquals($original->payload, $restored->payload);
        $this->assertEquals($original->headers, $restored->headers);
        $this->assertEquals($original->correlationId, $restored->correlationId);
        $this->assertEquals($original->metadata, $restored->metadata);
    }

    public function test_json_serializes(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: ['test' => 'data']
        );

        $json = json_encode($envelope, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals($envelope->messageId, $decoded['messageId']);
        $this->assertEquals($envelope->senderDid, $decoded['senderDid']);
        $this->assertEquals('request', $decoded['messageType']);
    }

    public function test_message_without_expiration_never_expires(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: []
            // No ttlSeconds specified
        );

        $this->assertNull($envelope->expiresAt);
        $this->assertFalse($envelope->isExpired());
    }

    public function test_conversation_id_defaults_to_message_id(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: []
        );

        $this->assertEquals($envelope->messageId, $envelope->conversationId);
    }

    public function test_explicit_conversation_id_is_preserved(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: [],
            conversationId: 'explicit_conversation_id'
        );

        $this->assertEquals('explicit_conversation_id', $envelope->conversationId);
    }
}
