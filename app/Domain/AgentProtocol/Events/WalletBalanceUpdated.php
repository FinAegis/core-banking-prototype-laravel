<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class WalletBalanceUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $walletId,
        public readonly float $previousBalance,
        public readonly float $newBalance,
        public readonly float $change,
        public readonly string $reason,
        public readonly array $metadata = []
    ) {
    }
}
