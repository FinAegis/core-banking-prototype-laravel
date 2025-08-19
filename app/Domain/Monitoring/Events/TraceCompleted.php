<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TraceCompleted extends ShouldBeStored
{
    public function __construct(
        public readonly string $traceId,
        public readonly float $duration,
        public readonly bool $hasErrors,
        public readonly int $spanCount,
        public readonly float $timestamp
    ) {
    }
}
