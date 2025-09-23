<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MessageAcknowledged extends ShouldBeStored
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $acknowledgedBy,
        public readonly ?string $acknowledgmentId,
        public readonly array $acknowledgmentData,
        public readonly string $acknowledgedAt
    ) {
    }
}
