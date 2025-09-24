<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentComplianceLinked extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $customerId,
        public readonly string $linkType,
        public readonly array $metadata = []
    ) {
    }
}
