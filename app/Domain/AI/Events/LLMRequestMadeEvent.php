<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LLMRequestMadeEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $userId,
        public readonly string $provider,
        public readonly string $message,
        public readonly array $options,
        public readonly string $timestamp
    ) {
    }
}
