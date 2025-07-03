<?php

namespace App\Domain\Cgo\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RefundFailed extends ShouldBeStored
{
    public static string $queue = 'events';

    public function __construct(
        public readonly string $refundId,
        public readonly string $failureReason,
        public readonly string $failedAt,
        public readonly array $metadata = []
    ) {}
}