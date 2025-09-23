<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MessageRetried extends ShouldBeStored
{
    public function __construct(
        public readonly string $messageId,
        public readonly int $retryCount,
        public readonly string $reason,
        public readonly int $nextRetryDelay,
        public readonly array $retryDetails,
        public readonly string $retriedAt
    ) {
    }
}
