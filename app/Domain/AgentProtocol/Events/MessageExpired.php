<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MessageExpired extends ShouldBeStored
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $reason,
        public readonly string $expiredAt
    ) {
    }
}
