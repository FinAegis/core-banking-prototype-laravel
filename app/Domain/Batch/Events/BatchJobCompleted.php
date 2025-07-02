<?php

namespace App\Domain\Batch\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class BatchJobCompleted extends ShouldBeStored
{
    /**
     * @var string
     */
    public string $queue = EventQueues::TRANSACTIONS->value;

    /**
     * @param string $completedAt
     * @param int $totalProcessed
     * @param int $totalFailed
     * @param string $finalStatus // completed, completed_with_errors, failed
     */
    public function __construct(
        public readonly string $completedAt,
        public readonly int $totalProcessed,
        public readonly int $totalFailed,
        public readonly string $finalStatus
    ) {}
}