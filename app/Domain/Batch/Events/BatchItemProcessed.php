<?php

namespace App\Domain\Batch\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class BatchItemProcessed extends ShouldBeStored
{
    /**
     * @var string
     */
    public string $queue = EventQueues::TRANSACTIONS->value;

    /**
     * @param int $itemIndex
     * @param string $status // completed, failed
     * @param array $result
     * @param ?string $errorMessage
     */
    public function __construct(
        public readonly int $itemIndex,
        public readonly string $status,
        public readonly array $result,
        public readonly ?string $errorMessage = null
    ) {}
}