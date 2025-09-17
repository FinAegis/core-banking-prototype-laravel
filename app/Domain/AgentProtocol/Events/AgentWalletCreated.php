<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentWalletCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $walletId,
        public readonly string $agentId,
        public readonly string $currency,
        public readonly float $initialBalance = 0.0,
        public readonly array $metadata = []
    ) {
    }
}
