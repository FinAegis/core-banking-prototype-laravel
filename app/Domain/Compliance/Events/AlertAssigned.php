<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AlertAssigned extends ShouldBeStored
{
    public function __construct(
        public readonly string $alertId,
        public readonly string $assignedTo,
        public readonly string $assignedBy,
        public readonly DateTimeImmutable $assignedAt,
        public readonly array $metadata
    ) {
    }
}
