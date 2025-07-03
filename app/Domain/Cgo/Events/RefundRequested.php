<?php

namespace App\Domain\Cgo\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RefundRequested extends ShouldBeStored
{
    public static string $queue = 'events';

    public function __construct(
        public readonly string $refundId,
        public readonly string $investmentId,
        public readonly string $userId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $reason,
        public readonly ?string $reasonDetails,
        public readonly string $initiatedBy,
        public readonly array $metadata = []
    ) {}
}