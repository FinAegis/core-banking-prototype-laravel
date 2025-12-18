<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Messaging;

use App\Domain\AgentProtocol\Enums\MessageStatus;

/**
 * Receipt returned when a message is sent through the message bus.
 */
final class MessageDeliveryReceipt
{
    public function __construct(
        public readonly string $messageId,
        public readonly MessageStatus $status,
        public readonly ?string $queuedAt = null,
        public readonly ?string $estimatedDeliveryTime = null,
        public readonly ?string $error = null,
        public readonly array $metadata = []
    ) {
    }

    /**
     * Check if the message was successfully queued.
     */
    public function isQueued(): bool
    {
        return $this->status === MessageStatus::QUEUED;
    }

    /**
     * Check if sending failed.
     */
    public function isFailed(): bool
    {
        return $this->status === MessageStatus::FAILED;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'messageId'             => $this->messageId,
            'status'                => $this->status->value,
            'queuedAt'              => $this->queuedAt,
            'estimatedDeliveryTime' => $this->estimatedDeliveryTime,
            'error'                 => $this->error,
            'metadata'              => $this->metadata,
        ];
    }
}
