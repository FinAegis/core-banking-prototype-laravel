<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentEndpointUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $endpointType,
        public readonly string $endpointUrl
    ) {
    }
}
