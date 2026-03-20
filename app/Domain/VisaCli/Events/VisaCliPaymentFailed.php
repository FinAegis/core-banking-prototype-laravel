<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class VisaCliPaymentFailed extends ShouldBeStored
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $paymentId,
        public readonly string $reason,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly array $metadata = [],
    ) {
    }
}
