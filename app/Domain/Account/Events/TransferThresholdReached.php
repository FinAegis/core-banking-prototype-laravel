<?php

namespace App\Domain\Account\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransferThresholdReached extends ShouldBeStored
{
    /**
     * @var string
     */
    public string $queue = EventQueues::TRANSFERS->value;
}