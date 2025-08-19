<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AlertTriggered extends ShouldBeStored
{
    public function __construct(
        public readonly string $aggregateId,
        public readonly string $alertId,
        public readonly string $level,
        public readonly string $message,
        public readonly array $context = [],
        public readonly ?\DateTimeImmutable $triggeredAt = null
    ) {
    }
}
