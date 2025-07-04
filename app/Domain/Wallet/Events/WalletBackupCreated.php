<?php

namespace App\Domain\Wallet\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class WalletBackupCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $walletId,
        public readonly string $backupId,
        public readonly string $backupMethod,
        public readonly string $encryptedData,
        public readonly string $createdBy,
        public readonly Carbon $createdAt
    ) {}
}