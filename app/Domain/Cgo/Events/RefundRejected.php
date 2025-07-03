<?php

namespace App\Domain\Cgo\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RefundRejected extends ShouldBeStored
{
    public static string $queue = 'events';

    public function __construct(
        public readonly string $refundId,
        public readonly string $rejectedBy,
        public readonly string $rejectionReason,
        public readonly array $metadata = []
    ) {}
}