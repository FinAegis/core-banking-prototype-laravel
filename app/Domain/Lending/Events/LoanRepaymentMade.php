<?php

namespace App\Domain\Lending\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanRepaymentMade extends ShouldBeStored
{
    public function __construct(
        public string $loanId,
        public int $paymentNumber,
        public string $amount,
        public string $principalAmount,
        public string $interestAmount,
        public string $remainingBalance,
        public DateTimeImmutable $paidAt
    ) {
    }
}
