<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class IntentClassifiedEvent extends ShouldBeStored
{
    public function __construct(
        public string $conversationId,
        public string $query,
        public string $intent,
        public float $confidence,
        public ?string $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? now()->toIso8601String();
    }

    public function tags(): array
    {
        return [
            'ai-intent',
            "intent:{$this->intent}",
            "conversation:{$this->conversationId}",
        ];
    }
}
