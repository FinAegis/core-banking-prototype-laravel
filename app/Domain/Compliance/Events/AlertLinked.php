<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AlertLinked extends ShouldBeStored
{
    public function __construct(
        public readonly string $alertId,
        public readonly array $linkedAlertIds,
        public readonly string $linkType,
        public readonly string $linkedBy,
        public readonly DateTimeImmutable $linkedAt
    ) {
    }
}
