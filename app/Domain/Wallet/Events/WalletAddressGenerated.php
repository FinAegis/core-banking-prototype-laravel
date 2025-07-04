<?php

namespace App\Domain\Wallet\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class WalletAddressGenerated extends ShouldBeStored
{
    public function __construct(
        public readonly string $walletId,
        public readonly string $chain,
        public readonly string $address,
        public readonly string $publicKey,
        public readonly string $derivationPath,
        public readonly ?string $label
    ) {}
}