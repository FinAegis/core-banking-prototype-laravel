<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MessageSent extends ShouldBeStored
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $fromAgentId,
        public readonly string $toAgentId,
        public readonly string $messageType,
        public readonly array $payload,
        public readonly array $headers,
        public readonly int $priority,
        public readonly ?string $correlationId,
        public readonly ?string $replyTo,
        public readonly string $sentAt,
        public readonly array $metadata
    ) {
    }
}
