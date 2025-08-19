<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class HealthCheckFailed extends ShouldBeStored
{
    public function __construct(
        public readonly string $component,
        public readonly string $message,
        public readonly array $details = []
    ) {
    }
}
