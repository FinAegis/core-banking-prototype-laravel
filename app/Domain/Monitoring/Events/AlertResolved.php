<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AlertResolved extends ShouldBeStored
{
    public function __construct(
        public readonly string $alertId,
        public readonly string $alertName,
        public readonly string $resolution,
        public readonly array $metadata = []
    ) {
    }
}
