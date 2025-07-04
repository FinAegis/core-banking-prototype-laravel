<?php

namespace App\Domain\Wallet\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class WalletKeyRotated extends ShouldBeStored
{
    public function __construct(
        public readonly string $walletId,
        public readonly string $chain,
        public readonly string $oldPublicKey,
        public readonly string $newPublicKey,
        public readonly string $rotatedBy,
        public readonly Carbon $rotatedAt
    ) {}
}