<?php

namespace App\Domain\Lending\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanApplicationRejected extends ShouldBeStored
{
    public function __construct(
        public string $applicationId,
        public array $reasons,
        public string $rejectedBy,
        public \DateTimeImmutable $rejectedAt
    ) {}
}