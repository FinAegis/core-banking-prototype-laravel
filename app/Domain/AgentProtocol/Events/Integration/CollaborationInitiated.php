<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollaborationInitiated extends ShouldBeStored
{
    public function __construct(
        public readonly string $collaborationId,
        public readonly string $taskId,
        public readonly string $taskType,
        public readonly array $participants,
        public readonly array $taskData
    ) {
    }
}
