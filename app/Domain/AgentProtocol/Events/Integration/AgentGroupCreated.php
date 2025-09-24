<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentGroupCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $groupId,
        public readonly string $groupName,
        public readonly array $memberIds,
        public readonly array $configuration
    ) {
    }
}
