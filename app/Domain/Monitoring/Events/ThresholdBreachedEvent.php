<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ThresholdBreachedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $metricName,
        public readonly float $currentValue,
        public readonly float $threshold,
        public readonly string $comparison,
        public readonly Carbon $breachedAt
    ) {
    }
}
