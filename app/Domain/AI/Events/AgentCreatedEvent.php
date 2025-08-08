<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentCreatedEvent extends ShouldBeStored
{
    public function __construct(
        public string $conversationId,
        public string $agentId,
        public string $agentType,
        public array $capabilities,
        public ?string $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? now()->toIso8601String();
    }

    public function tags(): array
    {
        return [
            'ai-agent',
            "agent:{$this->agentType}",
            "conversation:{$this->conversationId}",
        ];
    }
}
