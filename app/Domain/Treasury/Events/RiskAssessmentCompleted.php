<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RiskAssessmentCompleted extends ShouldBeStored
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $assessmentId,
        public readonly float $riskScore,
        public readonly string $riskLevel,
        public readonly array $riskFactors,
        public readonly array $recommendations,
        public readonly string $assessedBy
    ) {
    }
}
