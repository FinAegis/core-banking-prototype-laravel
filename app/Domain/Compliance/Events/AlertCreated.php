<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AlertCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $alertId,
        public readonly string $type,
        public readonly string $severity,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $description,
        public readonly array $details,
        public readonly array $metadata
    ) {
    }
}
