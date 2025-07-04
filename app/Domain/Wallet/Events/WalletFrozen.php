<?php

namespace App\Domain\Wallet\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class WalletFrozen extends ShouldBeStored
{
    public function __construct(
        public readonly string $walletId,
        public readonly string $reason,
        public readonly string $frozenBy,
        public readonly Carbon $frozenAt
    ) {}
}