<?php

namespace App\Domain\Batch\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class BatchJobStarted extends ShouldBeStored
{
    /**
     * @var string
     */
    public string $queue = EventQueues::TRANSACTIONS->value;

    /**
     * @param string $startedAt
     */
    public function __construct(
        public readonly string $startedAt
    ) {}
}