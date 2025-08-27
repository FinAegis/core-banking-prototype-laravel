<?php

namespace App\Domain\Lending\Events;

use App\Domain\Lending\ValueObjects\RepaymentSchedule;
use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanCreated extends ShouldBeStored
{
    public function __construct(
        public string $loanId,
        public string $applicationId,
        public string $borrowerId,
        public string $principal,
        public float $interestRate,
        public int $termMonths,
        public RepaymentSchedule $repaymentSchedule,
        public array $terms,
        public DateTimeImmutable $createdAt
    ) {
    }
}
