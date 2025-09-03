<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MonitoringRuleTriggered extends ShouldBeStored
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $entityId,
        public readonly string $entityType,
        public readonly string $ruleName,
        public readonly array $context,
        public readonly DateTimeImmutable $triggeredAt
    ) {
    }
}
