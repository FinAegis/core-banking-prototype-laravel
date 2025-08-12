<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class HumanInterventionRequestedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $reason,
        public readonly array $context,
        public readonly float $confidence,
        public readonly ?string $suggestedAction = null
    ) {
    }
}
