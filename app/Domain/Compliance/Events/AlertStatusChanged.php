<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AlertStatusChanged extends ShouldBeStored
{
    public function __construct(
        public readonly string $alertId,
        public readonly string $previousStatus,
        public readonly string $newStatus,
        public readonly string $changedBy,
        public readonly DateTimeImmutable $changedAt,
        public readonly array $metadata
    ) {
    }
}
