<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class SpanAttributeUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly string $key,
        public readonly mixed $value
    ) {
    }
}
