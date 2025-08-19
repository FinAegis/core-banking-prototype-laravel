<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class SpanStarted extends ShouldBeStored
{
    public function __construct(
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly ?string $parentSpanId,
        public readonly string $name,
        public readonly array $attributes,
        public readonly float $timestamp
    ) {
    }
}
