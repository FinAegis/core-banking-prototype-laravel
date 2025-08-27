<?php

namespace App\Domain\Lending\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanApplicationCreditCheckCompleted extends ShouldBeStored
{
    public function __construct(
        public string $applicationId,
        public int $score,
        public string $bureau,
        public array $report,
        public string $checkedBy,
        public DateTimeImmutable $checkedAt
    ) {
    }
}
