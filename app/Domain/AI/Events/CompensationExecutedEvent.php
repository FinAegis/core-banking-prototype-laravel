<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CompensationExecutedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $workflowId,
        public readonly string $reason,
        public readonly array $compensatedActions,
        public readonly bool $success,
        public readonly ?string $errorMessage = null
    ) {
    }
}
