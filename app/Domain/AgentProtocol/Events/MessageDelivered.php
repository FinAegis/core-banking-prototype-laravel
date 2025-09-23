<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MessageDelivered extends ShouldBeStored
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $toAgentId,
        public readonly string $deliveryMethod,
        public readonly array $deliveryDetails,
        public readonly string $deliveredAt
    ) {
    }
}
