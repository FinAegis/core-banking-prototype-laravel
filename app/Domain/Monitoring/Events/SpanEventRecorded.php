<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class SpanEventRecorded extends ShouldBeStored
{
    public function __construct(
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly string $eventName,
        public readonly array $attributes,
        public readonly float $timestamp
    ) {
    }
}
