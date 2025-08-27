<?php

namespace App\Domain\Lending\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanCompleted extends ShouldBeStored
{
    public function __construct(
        public string $loanId,
        public string $totalPrincipalPaid,
        public string $totalInterestPaid,
        public DateTimeImmutable $completedAt
    ) {
    }
}
