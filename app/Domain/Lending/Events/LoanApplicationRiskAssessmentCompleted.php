<?php

namespace App\Domain\Lending\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LoanApplicationRiskAssessmentCompleted extends ShouldBeStored
{
    public function __construct(
        public string $applicationId,
        public string $rating,
        public float $defaultProbability,
        public array $riskFactors,
        public string $assessedBy,
        public \DateTimeImmutable $assessedAt
    ) {}
}