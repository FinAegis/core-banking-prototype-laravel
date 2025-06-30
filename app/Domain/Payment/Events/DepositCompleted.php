<?php

namespace App\Domain\Payment\Events;

use App\Values\EventQueues;
use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class DepositCompleted extends ShouldBeStored
{
    public string $queue = EventQueues::TRANSACTIONS->value;
    public function __construct(
        public string $transactionId,
        public Carbon $completedAt
    ) {
    }
}