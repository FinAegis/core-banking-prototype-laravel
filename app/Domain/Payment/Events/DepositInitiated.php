<?php

namespace App\Domain\Payment\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class DepositInitiated extends ShouldBeStored
{
    public string $queue = EventQueues::TRANSACTIONS->value;
    public function __construct(
        public string $accountUuid,
        public int $amount,
        public string $currency,
        public string $reference,
        public string $externalReference,
        public string $paymentMethod,
        public string $paymentMethodType,
        public array $metadata = []
    ) {
    }
}