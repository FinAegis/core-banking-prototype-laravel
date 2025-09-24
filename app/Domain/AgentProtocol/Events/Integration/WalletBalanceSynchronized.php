<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Illuminate\Support\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class WalletBalanceSynchronized extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentWalletId,
        public readonly string $accountUuid,
        public readonly float $oldBalance,
        public readonly float $newBalance,
        public readonly Carbon $syncedAt
    ) {
    }
}
