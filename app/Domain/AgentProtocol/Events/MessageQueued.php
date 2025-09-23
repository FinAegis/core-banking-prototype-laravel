<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MessageQueued extends ShouldBeStored
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $queueName,
        public readonly int $priority,
        public readonly ?int $delay,
        public readonly ?string $processAfter,
        public readonly string $queuedAt
    ) {
    }
}
