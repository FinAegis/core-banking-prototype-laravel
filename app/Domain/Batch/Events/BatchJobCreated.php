<?php

namespace App\Domain\Batch\Events;

use App\Domain\Batch\DataObjects\BatchJob;
use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class BatchJobCreated extends ShouldBeStored
{
    /**
     * @var string
     */
    public string $queue = EventQueues::TRANSACTIONS->value;

    /**
     * @param BatchJob $batchJob
     */
    public function __construct(
        public readonly BatchJob $batchJob
    ) {}
}