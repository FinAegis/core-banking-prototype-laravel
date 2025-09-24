<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AIConversationInitiated extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $aiAgentId,
        public readonly string $userId,
        public readonly bool $paymentEnabled,
        public readonly array $context
    ) {
    }
}
