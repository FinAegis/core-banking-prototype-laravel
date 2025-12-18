<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Messaging;

use App\Domain\AgentProtocol\Enums\MessagePriority;
use App\Domain\AgentProtocol\Enums\MessageStatus;
use App\Domain\AgentProtocol\Enums\MessageType;
use App\Domain\AgentProtocol\Messaging\A2AMessageEnvelope;
use App\Domain\AgentProtocol\Messaging\AgentMessageBusService;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Services\DigitalSignatureService;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AgentMessageBusServiceTest extends TestCase
{
    private AgentMessageBusService $service;

    /** @var AgentRegistryService&MockInterface */
    private MockInterface $registryService;

    /** @var DigitalSignatureService&MockInterface */
    private MockInterface $signatureService;

    /** @var QueueFactory&MockInterface */
    private MockInterface $queueFactory;

    /** @var CacheRepository&MockInterface */
    private MockInterface $cache;

    /** @var Dispatcher&MockInterface */
    private MockInterface $events;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var AgentRegistryService&MockInterface $registryService */
        $registryService = Mockery::mock(AgentRegistryService::class);
        $this->registryService = $registryService;

        /** @var DigitalSignatureService&MockInterface $signatureService */
        $signatureService = Mockery::mock(DigitalSignatureService::class);
        $this->signatureService = $signatureService;

        /** @var QueueFactory&MockInterface $queueFactory */
        $queueFactory = Mockery::mock(QueueFactory::class);
        $this->queueFactory = $queueFactory;

        /** @var CacheRepository&MockInterface $cache */
        $cache = Mockery::mock(CacheRepository::class);
        $this->cache = $cache;

        /** @var Dispatcher&MockInterface $events */
        $events = Mockery::mock(Dispatcher::class);
        $this->events = $events;

        $this->service = new AgentMessageBusService(
            $this->registryService,
            $this->signatureService,
            $this->queueFactory,
            $this->cache,
            $this->events
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sends_message_successfully(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: ['test' => 'data']
        );

        // Mock agent validation
        $this->registryService
            ->shouldReceive('getAgent')
            ->with('did:finaegis:key:sender123')
            ->once()
            ->andReturn(['did' => 'did:finaegis:key:sender123']);

        $this->registryService
            ->shouldReceive('getAgent')
            ->with('did:finaegis:key:recipient456')
            ->once()
            ->andReturn(['did' => 'did:finaegis:key:recipient456']);

        // Mock signature
        $this->signatureService
            ->shouldReceive('signAgentTransaction')
            ->once()
            ->andReturn(['signature' => 'test_signature']);

        // Mock cache operations
        $this->cache
            ->shouldReceive('put')
            ->times(3); // message, conversation, status

        $this->cache
            ->shouldReceive('get')
            ->andReturn([]);

        // Mock queue
        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('pushOn')->once();
        $this->queueFactory->shouldReceive('connection')->andReturn($queue);

        // Mock events
        $this->events->shouldReceive('dispatch')->twice(); // sent and status_changed

        $receipt = $this->service->send($envelope);

        $this->assertEquals($envelope->messageId, $receipt->messageId);
        $this->assertEquals(MessageStatus::QUEUED, $receipt->status);
        $this->assertNotNull($receipt->queuedAt);
    }

    public function test_send_fails_with_unknown_sender(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:unknown',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: []
        );

        $this->registryService
            ->shouldReceive('getAgent')
            ->with('did:finaegis:key:unknown')
            ->once()
            ->andReturn(null);

        $receipt = $this->service->send($envelope);

        $this->assertEquals(MessageStatus::FAILED, $receipt->status);
        $this->assertStringContainsString('Unknown sender', $receipt->error);
    }

    public function test_send_fails_with_unknown_recipient(): void
    {
        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:unknown',
            messageType: MessageType::REQUEST,
            payload: []
        );

        $this->registryService
            ->shouldReceive('getAgent')
            ->with('did:finaegis:key:sender123')
            ->once()
            ->andReturn(['did' => 'did:finaegis:key:sender123']);

        $this->registryService
            ->shouldReceive('getAgent')
            ->with('did:finaegis:key:unknown')
            ->once()
            ->andReturn(null);

        $receipt = $this->service->send($envelope);

        $this->assertEquals(MessageStatus::FAILED, $receipt->status);
        $this->assertStringContainsString('Unknown recipient', $receipt->error);
    }

    public function test_registers_message_handler(): void
    {
        $handlerCalled = false;

        $this->service->registerHandler(
            MessageType::REQUEST,
            function (A2AMessageEnvelope $envelope) use (&$handlerCalled) {
                $handlerCalled = true;

                return ['processed' => true];
            }
        );

        // Create signed envelope
        $envelope = new A2AMessageEnvelope(
            messageId: 'msg_test',
            protocolVersion: '1.0',
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            priority: MessagePriority::NORMAL,
            payload: ['test' => 'data'],
            signature: 'valid_signature',
            requiresAcknowledgment: false
        );

        // Mock signature verification
        $this->signatureService
            ->shouldReceive('verifyAgentSignature')
            ->once()
            ->andReturn(['is_valid' => true]);

        // Mock cache operations
        $this->cache
            ->shouldReceive('has')
            ->once()
            ->andReturn(false);

        $this->cache
            ->shouldReceive('put')
            ->times(2); // processed marker and status

        // Mock events
        $this->events->shouldReceive('dispatch')->once();

        $result = $this->service->receive($envelope);

        $this->assertTrue($result->success);
        $this->assertTrue($handlerCalled);
    }

    public function test_receive_rejects_invalid_signature(): void
    {
        $envelope = new A2AMessageEnvelope(
            messageId: 'msg_test',
            protocolVersion: '1.0',
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            priority: MessagePriority::NORMAL,
            payload: [],
            signature: 'invalid_signature'
        );

        $this->signatureService
            ->shouldReceive('verifyAgentSignature')
            ->once()
            ->andReturn(['is_valid' => false]);

        $result = $this->service->receive($envelope);

        $this->assertFalse($result->success);
        $this->assertEquals('Invalid message signature', $result->error);
    }

    public function test_receive_rejects_expired_message(): void
    {
        $envelope = new A2AMessageEnvelope(
            messageId: 'msg_expired',
            protocolVersion: '1.0',
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            priority: MessagePriority::NORMAL,
            payload: [],
            signature: 'valid_signature',
            expiresAt: new DateTimeImmutable('-1 hour')
        );

        $this->signatureService
            ->shouldReceive('verifyAgentSignature')
            ->once()
            ->andReturn(['is_valid' => true]);

        $result = $this->service->receive($envelope);

        $this->assertFalse($result->success);
        $this->assertEquals('Message has expired', $result->error);
    }

    public function test_detects_duplicate_messages(): void
    {
        $envelope = new A2AMessageEnvelope(
            messageId: 'msg_duplicate',
            protocolVersion: '1.0',
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            priority: MessagePriority::NORMAL,
            payload: [],
            signature: 'valid_signature'
        );

        $this->signatureService
            ->shouldReceive('verifyAgentSignature')
            ->once()
            ->andReturn(['is_valid' => true]);

        // Message already processed
        $this->cache
            ->shouldReceive('has')
            ->with('a2a_message:processed:msg_duplicate')
            ->once()
            ->andReturn(true);

        $result = $this->service->receive($envelope);

        $this->assertTrue($result->success);
        $this->assertTrue($result->duplicate);
    }

    public function test_gets_message_status(): void
    {
        $this->cache
            ->shouldReceive('get')
            ->with('a2a_message:status:msg_123')
            ->once()
            ->andReturn('delivered');

        $status = $this->service->getMessageStatus('msg_123');

        $this->assertEquals(MessageStatus::DELIVERED, $status);
    }

    public function test_returns_null_for_unknown_message_status(): void
    {
        $this->cache
            ->shouldReceive('get')
            ->with('a2a_message:status:unknown')
            ->once()
            ->andReturn(null);

        $status = $this->service->getMessageStatus('unknown');

        $this->assertNull($status);
    }

    public function test_cancels_pending_message(): void
    {
        $this->cache
            ->shouldReceive('get')
            ->with('a2a_message:status:msg_123')
            ->once()
            ->andReturn('pending');

        $this->cache
            ->shouldReceive('put')
            ->once();

        $this->events
            ->shouldReceive('dispatch')
            ->twice(); // status_changed and cancelled

        $result = $this->service->cancel('msg_123');

        $this->assertTrue($result);
    }

    public function test_cannot_cancel_terminal_message(): void
    {
        $this->cache
            ->shouldReceive('get')
            ->with('a2a_message:status:msg_123')
            ->once()
            ->andReturn('acknowledged');

        $result = $this->service->cancel('msg_123');

        $this->assertFalse($result);
    }

    public function test_broadcasts_message_to_multiple_agents(): void
    {
        $recipients = [
            'did:finaegis:key:agent1',
            'did:finaegis:key:agent2',
            'did:finaegis:key:agent3',
        ];

        // Setup mocks for all sends
        $this->registryService
            ->shouldReceive('getAgent')
            ->andReturn(['did' => 'did:test']);

        $this->signatureService
            ->shouldReceive('signAgentTransaction')
            ->andReturn(['signature' => 'test_signature']);

        $this->cache
            ->shouldReceive('put')
            ->times(9); // 3 messages x 3 cache ops each

        $this->cache
            ->shouldReceive('get')
            ->andReturn([]);

        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('pushOn')->times(3);
        $this->queueFactory->shouldReceive('connection')->andReturn($queue);

        $this->events->shouldReceive('dispatch')->times(6); // 2 events per message

        $receipts = $this->service->broadcast(
            senderDid: 'did:finaegis:key:sender',
            recipientDids: $recipients,
            messageType: MessageType::NOTIFICATION,
            payload: ['announcement' => 'Test broadcast']
        );

        $this->assertCount(3, $receipts);
        foreach ($recipients as $recipient) {
            $this->assertArrayHasKey($recipient, $receipts);
        }
    }

    public function test_adds_middleware(): void
    {
        $middlewareExecuted = false;

        $this->service->addMiddleware('test', function (A2AMessageEnvelope $envelope) use (&$middlewareExecuted) {
            $middlewareExecuted = true;

            return $envelope;
        });

        $envelope = A2AMessageEnvelope::create(
            senderDid: 'did:finaegis:key:sender123',
            recipientDid: 'did:finaegis:key:recipient456',
            messageType: MessageType::REQUEST,
            payload: []
        );

        // Setup mocks
        $this->registryService
            ->shouldReceive('getAgent')
            ->andReturn(['did' => 'did:test']);

        $this->signatureService
            ->shouldReceive('signAgentTransaction')
            ->andReturn(['signature' => 'test_signature']);

        $this->cache
            ->shouldReceive('put')
            ->times(3);

        $this->cache
            ->shouldReceive('get')
            ->andReturn([]);

        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('pushOn')->once();
        $this->queueFactory->shouldReceive('connection')->andReturn($queue);

        $this->events->shouldReceive('dispatch')->twice();

        $this->service->send($envelope);

        $this->assertTrue($middlewareExecuted);
    }
}
