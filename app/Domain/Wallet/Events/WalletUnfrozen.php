<?php

namespace App\Domain\Wallet\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class WalletUnfrozen extends ShouldBeStored
{
    public function __construct(
        public readonly string $walletId,
        public readonly string $unfrozenBy,
        public readonly Carbon $unfrozenAt
    ) {}
}