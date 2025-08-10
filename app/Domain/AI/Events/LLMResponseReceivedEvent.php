<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LLMResponseReceivedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $provider,
        public readonly string $content,
        public readonly int $totalTokens,
        public readonly array $metadata,
        public readonly string $timestamp
    ) {
    }
}
