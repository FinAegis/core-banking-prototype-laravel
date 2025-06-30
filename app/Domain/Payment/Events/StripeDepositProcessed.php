<?php

namespace App\Domain\Payment\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class StripeDepositProcessed extends ShouldBeStored
{
    public function __construct(
        public string $accountUuid,
        public int $amount,
        public string $currency,
        public string $reference,
        public string $externalReference,
        public string $paymentMethod,
        public string $paymentMethodType,
        public array $metadata = []
    ) {}
}