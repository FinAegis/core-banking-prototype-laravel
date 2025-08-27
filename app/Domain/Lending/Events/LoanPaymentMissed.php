<?php

namespace App\Domain\Lending\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanPaymentMissed extends ShouldBeStored
{
    public function __construct(
        public string $loanId,
        public int $paymentNumber,
        public DateTimeImmutable $missedAt
    ) {
    }
}
