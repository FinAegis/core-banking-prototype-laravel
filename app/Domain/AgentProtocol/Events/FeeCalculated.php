<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class FeeCalculated extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly float $feeAmount,
        public readonly string $feeType,
        public readonly array $feeDetails = []
    ) {
    }
}
