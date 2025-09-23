<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\DataObjects;

class MessageDeliveryRequest
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $fromAgentId,
        public readonly string $toAgentId,
        public readonly string $messageType,
        public readonly array $payload,
        public readonly array $headers = [],
        public readonly int $priority = 50,
        public readonly ?string $correlationId = null,
        public readonly ?string $replyTo = null,
        public readonly bool $requiresAcknowledgment = true,
        public readonly ?int $acknowledgmentTimeout = null,
        public readonly ?string $queueName = null,
        public readonly bool $enableCompensation = false,
        public readonly array $metadata = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'messageId'              => $this->messageId,
            'fromAgentId'            => $this->fromAgentId,
            'toAgentId'              => $this->toAgentId,
            'messageType'            => $this->messageType,
            'payload'                => $this->payload,
            'headers'                => $this->headers,
            'priority'               => $this->priority,
            'correlationId'          => $this->correlationId,
            'replyTo'                => $this->replyTo,
            'requiresAcknowledgment' => $this->requiresAcknowledgment,
            'acknowledgmentTimeout'  => $this->acknowledgmentTimeout,
            'queueName'              => $this->queueName,
            'enableCompensation'     => $this->enableCompensation,
            'metadata'               => $this->metadata,
        ];
    }
}
