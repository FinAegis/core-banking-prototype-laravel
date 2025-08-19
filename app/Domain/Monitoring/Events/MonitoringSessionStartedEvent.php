<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MonitoringSessionStartedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $sessionId,
        public readonly Carbon $startedAt,
        public readonly array $metadata = []
    ) {
    }
}
