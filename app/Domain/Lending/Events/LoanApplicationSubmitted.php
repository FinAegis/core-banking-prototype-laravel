<?php

namespace App\Domain\Lending\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanApplicationSubmitted extends ShouldBeStored
{
    public function __construct(
        public string $applicationId,
        public string $borrowerId,
        public string $requestedAmount,
        public int $termMonths,
        public string $purpose,
        public array $borrowerInfo,
        public \DateTimeImmutable $submittedAt
    ) {}
}