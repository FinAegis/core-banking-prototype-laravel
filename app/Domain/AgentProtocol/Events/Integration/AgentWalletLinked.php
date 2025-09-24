<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentWalletLinked extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentWalletId,
        public readonly string $accountUuid,
        public readonly string $linkType,
        public readonly array $metadata = []
    ) {
    }
}
