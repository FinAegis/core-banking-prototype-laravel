<?php

namespace App\Domain\Lending\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RepaymentReceived extends ShouldBeStored
{
    public function __construct(
        public string $loanId,
        public int $paymentNumber,
        public string $amount,
        public string $principalPortion,
        public string $interestPortion,
        public array $metadata,
        public \DateTimeImmutable $receivedAt
    ) {}
}