<?php

namespace App\Domain\Lending\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanApplicationWithdrawn extends ShouldBeStored
{
    public function __construct(
        public string $applicationId,
        public string $reason,
        public string $withdrawnBy,
        public DateTimeImmutable $withdrawnAt
    ) {
    }
}
