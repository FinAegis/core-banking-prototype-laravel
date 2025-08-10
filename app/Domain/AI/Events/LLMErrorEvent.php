<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LLMErrorEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $provider,
        public readonly string $error,
        public readonly string $timestamp
    ) {
    }
}
