<?php

namespace App\Domain\Batch\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class BatchJobCancelled extends ShouldBeStored
{
    /**
     * @var string
     */
    public string $queue = EventQueues::TRANSACTIONS->value;

    /**
     * @param string $reason
     * @param string $cancelledAt
     */
    public function __construct(
        public readonly string $reason,
        public readonly string $cancelledAt
    ) {}
}