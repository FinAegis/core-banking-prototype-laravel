<?php

namespace App\Domain\Payment\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class BankWithdrawalCompleted extends ShouldBeStored
{
    public function __construct(
        public string $accountUuid,
        public string $reference,
        public string $transferId,
        public string $status,
        public ?string $failureReason = null,
        public array $metadata = []
    ) {}
}