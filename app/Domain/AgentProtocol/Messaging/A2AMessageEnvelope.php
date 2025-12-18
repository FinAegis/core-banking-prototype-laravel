<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Messaging;

use App\Domain\AgentProtocol\Enums\MessagePriority;
use App\Domain\AgentProtocol\Enums\MessageStatus;
use App\Domain\AgentProtocol\Enums\MessageType;
use DateTimeImmutable;
use JsonSerializable;

/**
 * A2A Message Envelope following the Agent-to-Agent Protocol specification.
 *
 * This envelope wraps all A2A messages with metadata required for routing,
 * delivery, and tracking.
 */
final class A2AMessageEnvelope implements JsonSerializable
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $protocolVersion,
        public readonly string $senderDid,
        public readonly string $recipientDid,
        public readonly MessageType $messageType,
        public readonly MessagePriority $priority,
        public readonly array $payload,
        public readonly array $headers = [],
        public readonly ?string $correlationId = null,
        public readonly ?string $conversationId = null,
        public readonly ?string $replyTo = null,
        public readonly ?string $inReplyTo = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
        public readonly ?int $ttlSeconds = null,
        public readonly bool $requiresAcknowledgment = true,
        public readonly ?int $acknowledgmentTimeout = null,
        public readonly bool $enableRetry = true,
        public readonly int $maxRetries = 3,
        public readonly ?string $signature = null,
        public readonly ?string $encryptionKeyId = null,
        public readonly bool $isEncrypted = false,
        public readonly MessageStatus $status = MessageStatus::PENDING,
        public readonly array $metadata = []
    ) {
    }

    /**
     * Create a new message envelope with auto-generated ID and timestamp.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $senderDid,
        string $recipientDid,
        MessageType $messageType,
        array $payload,
        MessagePriority $priority = MessagePriority::NORMAL,
        array $headers = [],
        ?string $correlationId = null,
        ?string $conversationId = null,
        ?int $ttlSeconds = null,
        array $metadata = []
    ): self {
        $messageId = self::generateMessageId();
        $now = new DateTimeImmutable();

        return new self(
            messageId: $messageId,
            protocolVersion: '1.0',
            senderDid: $senderDid,
            recipientDid: $recipientDid,
            messageType: $messageType,
            priority: $priority,
            payload: $payload,
            headers: $headers,
            correlationId: $correlationId,
            conversationId: $conversationId ?? $messageId,
            createdAt: $now,
            expiresAt: $ttlSeconds ? $now->modify("+{$ttlSeconds} seconds") : null,
            ttlSeconds: $ttlSeconds,
            metadata: $metadata
        );
    }

    /**
     * Create a response envelope for this message.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    public function createResponse(
        array $payload,
        MessageType $responseType = MessageType::RESPONSE,
        array $headers = []
    ): self {
        return new self(
            messageId: self::generateMessageId(),
            protocolVersion: $this->protocolVersion,
            senderDid: $this->recipientDid,
            recipientDid: $this->senderDid,
            messageType: $responseType,
            priority: $this->priority,
            payload: $payload,
            headers: $headers,
            correlationId: $this->messageId,
            conversationId: $this->conversationId,
            inReplyTo: $this->messageId,
            createdAt: new DateTimeImmutable(),
            requiresAcknowledgment: false,
        );
    }

    /**
     * Create an acknowledgment for this message.
     */
    public function createAcknowledgment(bool $success = true, ?string $errorMessage = null): self
    {
        return new self(
            messageId: self::generateMessageId(),
            protocolVersion: $this->protocolVersion,
            senderDid: $this->recipientDid,
            recipientDid: $this->senderDid,
            messageType: MessageType::ACKNOWLEDGMENT,
            priority: MessagePriority::HIGH,
            payload: [
                'acknowledged'      => $success,
                'originalMessageId' => $this->messageId,
                'error'             => $errorMessage,
            ],
            correlationId: $this->messageId,
            conversationId: $this->conversationId,
            inReplyTo: $this->messageId,
            createdAt: new DateTimeImmutable(),
            requiresAcknowledgment: false,
        );
    }

    /**
     * Check if the message has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return new DateTimeImmutable() > $this->expiresAt;
    }

    /**
     * Check if the message requires a response.
     */
    public function requiresResponse(): bool
    {
        return $this->messageType->requiresResponse();
    }

    /**
     * Get the queue name based on priority.
     */
    public function getQueueName(): string
    {
        return $this->priority->getQueueName();
    }

    /**
     * Create a copy with updated status.
     */
    public function withStatus(MessageStatus $status): self
    {
        return new self(
            messageId: $this->messageId,
            protocolVersion: $this->protocolVersion,
            senderDid: $this->senderDid,
            recipientDid: $this->recipientDid,
            messageType: $this->messageType,
            priority: $this->priority,
            payload: $this->payload,
            headers: $this->headers,
            correlationId: $this->correlationId,
            conversationId: $this->conversationId,
            replyTo: $this->replyTo,
            inReplyTo: $this->inReplyTo,
            createdAt: $this->createdAt,
            expiresAt: $this->expiresAt,
            ttlSeconds: $this->ttlSeconds,
            requiresAcknowledgment: $this->requiresAcknowledgment,
            acknowledgmentTimeout: $this->acknowledgmentTimeout,
            enableRetry: $this->enableRetry,
            maxRetries: $this->maxRetries,
            signature: $this->signature,
            encryptionKeyId: $this->encryptionKeyId,
            isEncrypted: $this->isEncrypted,
            status: $status,
            metadata: $this->metadata
        );
    }

    /**
     * Create a copy with signature.
     */
    public function withSignature(string $signature): self
    {
        return new self(
            messageId: $this->messageId,
            protocolVersion: $this->protocolVersion,
            senderDid: $this->senderDid,
            recipientDid: $this->recipientDid,
            messageType: $this->messageType,
            priority: $this->priority,
            payload: $this->payload,
            headers: $this->headers,
            correlationId: $this->correlationId,
            conversationId: $this->conversationId,
            replyTo: $this->replyTo,
            inReplyTo: $this->inReplyTo,
            createdAt: $this->createdAt,
            expiresAt: $this->expiresAt,
            ttlSeconds: $this->ttlSeconds,
            requiresAcknowledgment: $this->requiresAcknowledgment,
            acknowledgmentTimeout: $this->acknowledgmentTimeout,
            enableRetry: $this->enableRetry,
            maxRetries: $this->maxRetries,
            signature: $signature,
            encryptionKeyId: $this->encryptionKeyId,
            isEncrypted: $this->isEncrypted,
            status: $this->status,
            metadata: $this->metadata
        );
    }

    /**
     * Generate a unique message ID.
     */
    private static function generateMessageId(): string
    {
        return sprintf(
            'msg_%s_%s',
            bin2hex(random_bytes(8)),
            (string) hrtime(true)
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'messageId'              => $this->messageId,
            'protocolVersion'        => $this->protocolVersion,
            'senderDid'              => $this->senderDid,
            'recipientDid'           => $this->recipientDid,
            'messageType'            => $this->messageType->value,
            'priority'               => $this->priority->value,
            'payload'                => $this->payload,
            'headers'                => $this->headers,
            'correlationId'          => $this->correlationId,
            'conversationId'         => $this->conversationId,
            'replyTo'                => $this->replyTo,
            'inReplyTo'              => $this->inReplyTo,
            'createdAt'              => $this->createdAt?->format('c'),
            'expiresAt'              => $this->expiresAt?->format('c'),
            'ttlSeconds'             => $this->ttlSeconds,
            'requiresAcknowledgment' => $this->requiresAcknowledgment,
            'acknowledgmentTimeout'  => $this->acknowledgmentTimeout,
            'enableRetry'            => $this->enableRetry,
            'maxRetries'             => $this->maxRetries,
            'signature'              => $this->signature,
            'encryptionKeyId'        => $this->encryptionKeyId,
            'isEncrypted'            => $this->isEncrypted,
            'status'                 => $this->status->value,
            'metadata'               => $this->metadata,
        ];
    }

    /**
     * Create from array representation.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['messageId'],
            protocolVersion: $data['protocolVersion'] ?? '1.0',
            senderDid: $data['senderDid'],
            recipientDid: $data['recipientDid'],
            messageType: MessageType::from($data['messageType']),
            priority: MessagePriority::from((int) $data['priority']),
            payload: $data['payload'] ?? [],
            headers: $data['headers'] ?? [],
            correlationId: $data['correlationId'] ?? null,
            conversationId: $data['conversationId'] ?? null,
            replyTo: $data['replyTo'] ?? null,
            inReplyTo: $data['inReplyTo'] ?? null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
            expiresAt: isset($data['expiresAt']) ? new DateTimeImmutable($data['expiresAt']) : null,
            ttlSeconds: $data['ttlSeconds'] ?? null,
            requiresAcknowledgment: $data['requiresAcknowledgment'] ?? true,
            acknowledgmentTimeout: $data['acknowledgmentTimeout'] ?? null,
            enableRetry: $data['enableRetry'] ?? true,
            maxRetries: $data['maxRetries'] ?? 3,
            signature: $data['signature'] ?? null,
            encryptionKeyId: $data['encryptionKeyId'] ?? null,
            isEncrypted: $data['isEncrypted'] ?? false,
            status: MessageStatus::from($data['status'] ?? 'pending'),
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
