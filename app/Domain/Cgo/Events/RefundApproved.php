<?php

namespace App\Domain\Cgo\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RefundApproved extends ShouldBeStored
{
    public static string $queue = 'events';

    public function __construct(
        public readonly string $refundId,
        public readonly string $approvedBy,
        public readonly string $approvalNotes,
        public readonly array $metadata = []
    ) {}
}