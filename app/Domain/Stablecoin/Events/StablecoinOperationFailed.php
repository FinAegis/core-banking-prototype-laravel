<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use App\Domain\Shared\Enums\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class StablecoinOperationFailed extends ShouldBeStored
{
    public string $queue = EventQueues::TRANSACTIONS->value;

    public function __construct(
        public readonly string $uuid,
        public readonly string $reason
    ) {}
}