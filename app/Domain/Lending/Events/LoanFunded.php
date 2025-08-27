<?php

namespace App\Domain\Lending\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanFunded extends ShouldBeStored
{
    public function __construct(
        public string $loanId,
        public array $investorIds,
        public string $fundedAmount,
        public DateTimeImmutable $fundedAt
    ) {
    }
}
