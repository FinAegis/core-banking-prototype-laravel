<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AlertEscalatedToCase extends ShouldBeStored
{
    public function __construct(
        public readonly string $alertId,
        public readonly string $caseId,
        public readonly string $escalatedBy,
        public readonly string $reason,
        public readonly DateTimeImmutable $escalatedAt
    ) {
    }
}
