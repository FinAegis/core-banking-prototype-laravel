<?php

namespace App\Domain\Cgo\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RefundCompleted extends ShouldBeStored
{
    public static string $queue = 'events';

    public function __construct(
        public readonly string $refundId,
        public readonly string $investmentId,
        public readonly int $amountRefunded,
        public readonly string $completedAt,
        public readonly array $metadata = []
    ) {}
}