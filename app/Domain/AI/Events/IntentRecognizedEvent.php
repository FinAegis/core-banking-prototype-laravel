<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class IntentRecognizedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $intentType,
        public readonly float $confidence,
        public readonly array $entities,
        public readonly array $metadata = []
    ) {
    }
}
