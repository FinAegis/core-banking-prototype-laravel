<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Messaging;

use App\Domain\AgentProtocol\Enums\MessagePriority;
use App\Domain\AgentProtocol\Enums\MessageStatus;
use App\Domain\AgentProtocol\Enums\MessageType;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Services\DigitalSignatureService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Agent Message Bus Service for A2A communication.
 *
 * Handles message routing, delivery, and tracking for agent-to-agent messaging.
 */
class AgentMessageBusService
{
    private const CACHE_PREFIX = 'a2a_message:';

    private const CONVERSATION_PREFIX = 'a2a_conversation:';

    private const DEFAULT_TTL = 3600; // 1 hour

    /**
     * @var array<string, callable>
     */
    private array $handlers = [];

    /**
     * @var array<string, callable>
     */
    private array $middleware = [];

    public function __construct(
        private readonly AgentRegistryService $registryService,
        private readonly DigitalSignatureService $signatureService,
        private readonly QueueFactory $queueFactory,
        private readonly CacheRepository $cache,
        private readonly Dispatcher $events
    ) {
    }

    /**
     * Send a message to another agent.
     */
    public function send(A2AMessageEnvelope $envelope): MessageDeliveryReceipt
    {
        try {
            // Validate sender and recipient exist
            $this->validateAgents($envelope);

            // Apply middleware
            $envelope = $this->applyMiddleware($envelope);

            // Sign the message if not already signed
            if ($envelope->signature === null) {
                $envelope = $this->signMessage($envelope);
            }

            // Store message for tracking
            $this->storeMessage($envelope);

            // Queue for delivery based on priority
            $this->queueMessage($envelope);

            // Update status
            $this->updateMessageStatus($envelope->messageId, MessageStatus::QUEUED);

            // Dispatch event
            $this->events->dispatch('a2a.message.sent', [
                'messageId'    => $envelope->messageId,
                'senderDid'    => $envelope->senderDid,
                'recipientDid' => $envelope->recipientDid,
                'messageType'  => $envelope->messageType->value,
            ]);

            return new MessageDeliveryReceipt(
                messageId: $envelope->messageId,
                status: MessageStatus::QUEUED,
                queuedAt: now()->toIso8601String(),
                estimatedDeliveryTime: $this->estimateDeliveryTime($envelope)
            );
        } catch (Throwable $e) {
            Log::error('Failed to send A2A message', [
                'messageId' => $envelope->messageId,
                'error'     => $e->getMessage(),
            ]);

            return new MessageDeliveryReceipt(
                messageId: $envelope->messageId,
                status: MessageStatus::FAILED,
                error: $e->getMessage()
            );
        }
    }

    /**
     * Send a request and wait for response.
     *
     * @param int $timeout Timeout in seconds
     */
    public function sendAndWait(
        A2AMessageEnvelope $envelope,
        int $timeout = 30
    ): ?A2AMessageEnvelope {
        $receipt = $this->send($envelope);

        if ($receipt->status === MessageStatus::FAILED) {
            return null;
        }

        // Wait for response
        $responseKey = self::CACHE_PREFIX . 'response:' . $envelope->messageId;
        $startTime = time();

        while ((time() - $startTime) < $timeout) {
            $response = $this->cache->get($responseKey);
            if ($response !== null) {
                $this->cache->forget($responseKey);

                return A2AMessageEnvelope::fromArray($response);
            }
            usleep(100000); // 100ms polling interval
        }

        return null;
    }

    /**
     * Process an incoming message.
     */
    public function receive(A2AMessageEnvelope $envelope): ProcessingResult
    {
        try {
            // Verify signature
            if (! $this->verifySignature($envelope)) {
                return new ProcessingResult(
                    success: false,
                    error: 'Invalid message signature'
                );
            }

            // Check if message has expired
            if ($envelope->isExpired()) {
                return new ProcessingResult(
                    success: false,
                    error: 'Message has expired'
                );
            }

            // Check for duplicate delivery
            if ($this->isAlreadyProcessed($envelope->messageId)) {
                return new ProcessingResult(
                    success: true,
                    duplicate: true
                );
            }

            // Mark as being processed
            $this->markAsProcessed($envelope->messageId);

            // Update status
            $this->updateMessageStatus($envelope->messageId, MessageStatus::DELIVERING);

            // Route to appropriate handler
            $result = $this->routeToHandler($envelope);

            // Send acknowledgment if required
            if ($envelope->requiresAcknowledgment) {
                $this->sendAcknowledgment($envelope, $result->success, $result->error);
            }

            // If this is a response, store it for sender
            if ($envelope->inReplyTo !== null) {
                $this->storeResponse($envelope);
            }

            // Update status
            $this->updateMessageStatus(
                $envelope->messageId,
                $result->success ? MessageStatus::DELIVERED : MessageStatus::FAILED
            );

            return $result;
        } catch (Throwable $e) {
            Log::error('Failed to process A2A message', [
                'messageId' => $envelope->messageId,
                'error'     => $e->getMessage(),
            ]);

            return new ProcessingResult(
                success: false,
                error: $e->getMessage()
            );
        }
    }

    /**
     * Register a message handler for a specific message type.
     */
    public function registerHandler(MessageType $messageType, callable $handler): void
    {
        $this->handlers[$messageType->value] = $handler;
    }

    /**
     * Add middleware to the message processing pipeline.
     */
    public function addMiddleware(string $name, callable $middleware): void
    {
        $this->middleware[$name] = $middleware;
    }

    /**
     * Get message status.
     */
    public function getMessageStatus(string $messageId): ?MessageStatus
    {
        $statusValue = $this->cache->get(self::CACHE_PREFIX . 'status:' . $messageId);

        return $statusValue !== null ? MessageStatus::from($statusValue) : null;
    }

    /**
     * Get conversation history.
     *
     * @return array<A2AMessageEnvelope>
     */
    public function getConversation(string $conversationId): array
    {
        $messageIds = $this->cache->get(self::CONVERSATION_PREFIX . $conversationId, []);
        $messages = [];

        foreach ($messageIds as $messageId) {
            $messageData = $this->cache->get(self::CACHE_PREFIX . $messageId);
            if ($messageData !== null) {
                $messages[] = A2AMessageEnvelope::fromArray($messageData);
            }
        }

        return $messages;
    }

    /**
     * Broadcast a message to multiple agents.
     *
     * @param array<string> $recipientDids
     * @return array<string, MessageDeliveryReceipt>
     */
    public function broadcast(
        string $senderDid,
        array $recipientDids,
        MessageType $messageType,
        array $payload,
        MessagePriority $priority = MessagePriority::NORMAL
    ): array {
        $receipts = [];

        foreach ($recipientDids as $recipientDid) {
            $envelope = A2AMessageEnvelope::create(
                senderDid: $senderDid,
                recipientDid: $recipientDid,
                messageType: $messageType,
                payload: $payload,
                priority: $priority
            );

            $receipts[$recipientDid] = $this->send($envelope);
        }

        return $receipts;
    }

    /**
     * Subscribe to messages of a specific type.
     */
    public function subscribe(string $agentDid, MessageType $messageType, callable $callback): void
    {
        $subscriptionKey = "subscription:{$agentDid}:{$messageType->value}";
        $this->handlers[$subscriptionKey] = $callback;
    }

    /**
     * Cancel a pending message.
     */
    public function cancel(string $messageId): bool
    {
        $status = $this->getMessageStatus($messageId);

        if ($status === null || $status->isTerminal()) {
            return false;
        }

        $this->updateMessageStatus($messageId, MessageStatus::CANCELLED);
        $this->events->dispatch('a2a.message.cancelled', ['messageId' => $messageId]);

        return true;
    }

    /**
     * Retry a failed message.
     */
    public function retry(string $messageId): ?MessageDeliveryReceipt
    {
        $messageData = $this->cache->get(self::CACHE_PREFIX . $messageId);

        if ($messageData === null) {
            return null;
        }

        $envelope = A2AMessageEnvelope::fromArray($messageData);

        if (! $envelope->status->canRetry()) {
            return new MessageDeliveryReceipt(
                messageId: $messageId,
                status: MessageStatus::FAILED,
                error: 'Message cannot be retried from status: ' . $envelope->status->value
            );
        }

        // Reset status and re-send
        $envelope = $envelope->withStatus(MessageStatus::PENDING);

        return $this->send($envelope);
    }

    private function validateAgents(A2AMessageEnvelope $envelope): void
    {
        // Verify sender exists
        $sender = $this->registryService->getAgent($envelope->senderDid);
        if ($sender === null) {
            throw new InvalidArgumentException("Unknown sender: {$envelope->senderDid}");
        }

        // Verify recipient exists
        $recipient = $this->registryService->getAgent($envelope->recipientDid);
        if ($recipient === null) {
            throw new InvalidArgumentException("Unknown recipient: {$envelope->recipientDid}");
        }
    }

    private function applyMiddleware(A2AMessageEnvelope $envelope): A2AMessageEnvelope
    {
        foreach ($this->middleware as $middleware) {
            $envelope = $middleware($envelope);
        }

        return $envelope;
    }

    private function signMessage(A2AMessageEnvelope $envelope): A2AMessageEnvelope
    {
        $transactionData = [
            'messageId'    => $envelope->messageId,
            'senderDid'    => $envelope->senderDid,
            'recipientDid' => $envelope->recipientDid,
            'payload'      => $envelope->payload,
            'createdAt'    => $envelope->createdAt?->format('c'),
        ];

        $signatureResult = $this->signatureService->signAgentTransaction(
            transactionId: $envelope->messageId,
            agentId: $envelope->senderDid,
            transactionData: $transactionData,
            options: ['ttl' => 60]
        );

        $signature = $signatureResult['signature'] ?? json_encode($signatureResult, JSON_THROW_ON_ERROR);

        return $envelope->withSignature($signature);
    }

    private function verifySignature(A2AMessageEnvelope $envelope): bool
    {
        if ($envelope->signature === null) {
            return false;
        }

        $transactionData = [
            'messageId'    => $envelope->messageId,
            'senderDid'    => $envelope->senderDid,
            'recipientDid' => $envelope->recipientDid,
            'payload'      => $envelope->payload,
            'createdAt'    => $envelope->createdAt?->format('c'),
        ];

        $verificationResult = $this->signatureService->verifyAgentSignature(
            transactionId: $envelope->messageId,
            agentId: $envelope->senderDid,
            transactionData: $transactionData,
            signature: $envelope->signature,
            metadata: ['algorithm' => 'RS256']
        );

        return $verificationResult['is_valid'] ?? false;
    }

    private function storeMessage(A2AMessageEnvelope $envelope): void
    {
        $ttl = $envelope->ttlSeconds ?? self::DEFAULT_TTL;

        // Store message
        $this->cache->put(
            self::CACHE_PREFIX . $envelope->messageId,
            $envelope->toArray(),
            $ttl
        );

        // Add to conversation
        if ($envelope->conversationId !== null) {
            $conversationKey = self::CONVERSATION_PREFIX . $envelope->conversationId;
            $messageIds = $this->cache->get($conversationKey, []);
            $messageIds[] = $envelope->messageId;
            $this->cache->put($conversationKey, $messageIds, $ttl);
        }
    }

    private function storeResponse(A2AMessageEnvelope $envelope): void
    {
        $responseKey = self::CACHE_PREFIX . 'response:' . $envelope->inReplyTo;
        $this->cache->put($responseKey, $envelope->toArray(), 300); // 5 minute TTL for responses
    }

    private function queueMessage(A2AMessageEnvelope $envelope): void
    {
        $queueName = $envelope->getQueueName();
        $queue = $this->queueFactory->connection();

        $queue->pushOn($queueName, new ProcessA2AMessageJob($envelope->toArray()));
    }

    private function updateMessageStatus(string $messageId, MessageStatus $status): void
    {
        $this->cache->put(
            self::CACHE_PREFIX . 'status:' . $messageId,
            $status->value,
            self::DEFAULT_TTL
        );

        $this->events->dispatch('a2a.message.status_changed', [
            'messageId' => $messageId,
            'status'    => $status->value,
        ]);
    }

    private function isAlreadyProcessed(string $messageId): bool
    {
        return $this->cache->has(self::CACHE_PREFIX . 'processed:' . $messageId);
    }

    private function markAsProcessed(string $messageId): void
    {
        $this->cache->put(
            self::CACHE_PREFIX . 'processed:' . $messageId,
            true,
            self::DEFAULT_TTL
        );
    }

    private function routeToHandler(A2AMessageEnvelope $envelope): ProcessingResult
    {
        $handler = $this->handlers[$envelope->messageType->value] ?? null;

        if ($handler === null) {
            // Check for subscription-based handler
            $subscriptionKey = "subscription:{$envelope->recipientDid}:{$envelope->messageType->value}";
            $handler = $this->handlers[$subscriptionKey] ?? null;
        }

        if ($handler === null) {
            return new ProcessingResult(
                success: false,
                error: 'No handler registered for message type: ' . $envelope->messageType->value
            );
        }

        try {
            $result = $handler($envelope);

            return new ProcessingResult(
                success: true,
                data: $result
            );
        } catch (Throwable $e) {
            return new ProcessingResult(
                success: false,
                error: $e->getMessage()
            );
        }
    }

    private function sendAcknowledgment(A2AMessageEnvelope $original, bool $success, ?string $error): void
    {
        $ack = $original->createAcknowledgment($success, $error);
        $this->send($ack);
    }

    private function estimateDeliveryTime(A2AMessageEnvelope $envelope): string
    {
        $seconds = $envelope->priority->getMaxProcessingTime();

        return now()->addSeconds($seconds)->toIso8601String();
    }
}
