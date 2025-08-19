<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TracingSpanRecordedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly ?string $parentSpanId,
        public readonly string $operationName,
        public readonly float $duration,
        public readonly array $tags,
        public readonly Carbon $timestamp
    ) {
    }
}
