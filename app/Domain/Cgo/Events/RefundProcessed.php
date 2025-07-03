<?php

namespace App\Domain\Cgo\Events;

use App\Values\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RefundProcessed extends ShouldBeStored
{
    public static string $queue = 'events';

    public function __construct(
        public readonly string $refundId,
        public readonly string $paymentProcessor,
        public readonly string $processorRefundId,
        public readonly string $status,
        public readonly array $processorResponse,
        public readonly array $metadata = []
    ) {}
}