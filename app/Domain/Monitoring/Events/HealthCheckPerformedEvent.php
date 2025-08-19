<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class HealthCheckPerformedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $component,
        public readonly bool $isHealthy,
        public readonly ?string $message,
        public readonly array $details,
        public readonly Carbon $checkedAt
    ) {
    }
}
