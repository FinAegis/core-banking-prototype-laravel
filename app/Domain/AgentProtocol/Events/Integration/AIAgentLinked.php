<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Illuminate\Support\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AIAgentLinked extends ShouldBeStored
{
    public function __construct(
        public readonly string $aiAgentId,
        public readonly string $protocolAgentId,
        public readonly array $capabilities,
        public readonly Carbon $linkedAt
    ) {
    }
}
