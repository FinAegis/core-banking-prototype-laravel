<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CashAllocated extends ShouldBeStored
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $allocationId,
        public readonly string $strategy,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $allocations,
        public readonly string $allocatedBy
    ) {
    }
}
