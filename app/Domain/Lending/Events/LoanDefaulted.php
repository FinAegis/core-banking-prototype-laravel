<?php

namespace App\Domain\Lending\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanDefaulted extends ShouldBeStored
{
    public function __construct(
        public string $loanId,
        public string $reason,
        public string $outstandingBalance,
        public \DateTimeImmutable $defaultedAt
    ) {}
}