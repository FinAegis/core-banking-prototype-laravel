<?php

namespace App\Domain\Wallet\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class WalletSettingsUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $walletId,
        public readonly array $oldSettings,
        public readonly array $newSettings
    ) {}
}