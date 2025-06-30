<?php

namespace App\Domain\Payment\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class BankWithdrawalRequested extends ShouldBeStored
{
    public function __construct(
        public string $accountUuid,
        public int $amount,
        public string $currency,
        public string $reference,
        public string $bankName,
        public string $accountNumber,
        public string $accountHolderName,
        public ?string $routingNumber = null,
        public ?string $iban = null,
        public ?string $swift = null,
        public array $metadata = []
    ) {}
}