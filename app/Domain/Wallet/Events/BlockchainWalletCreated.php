<?php

namespace App\Domain\Wallet\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class BlockchainWalletCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $walletId,
        public readonly string $userId,
        public readonly string $type,
        public readonly ?string $masterPublicKey,
        public readonly array $settings
    ) {}
}