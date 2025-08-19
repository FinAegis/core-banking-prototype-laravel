<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MetricRecordedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $metricName,
        public readonly float $value,
        public readonly string $type,
        public readonly array $labels,
        public readonly ?string $unit,
        public readonly Carbon $timestamp
    ) {
    }
}
