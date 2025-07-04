<?php

namespace App\Domain\Lending\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanSettledEarly extends ShouldBeStored
{
    public function __construct(
        public string $loanId,
        public string $settlementAmount,
        public string $outstandingBalance,
        public string $settledBy,
        public \DateTimeImmutable $settledAt
    ) {}
}