<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ConversationStartedEvent extends ShouldBeStored
{
    public function __construct(
        public string $conversationId,
        public string $agentType,
        public ?string $userId,
        public array $initialContext,
        public ?string $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? now()->toIso8601String();
    }

    public function tags(): array
    {
        return [
            'ai-conversation',
            "agent:{$this->agentType}",
            "conversation:{$this->conversationId}",
        ];
    }
}
