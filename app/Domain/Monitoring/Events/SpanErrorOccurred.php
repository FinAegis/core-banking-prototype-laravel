<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class SpanErrorOccurred extends ShouldBeStored
{
    public function __construct(
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly string $message,
        public readonly string $type,
        public readonly string $stackTrace,
        public readonly array $context,
        public readonly float $timestamp
    ) {
    }
}
