<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class VisaCliCardEnrolled extends ShouldBeStored
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $cardIdentifier,
        public readonly string $last4,
        public readonly string $network,
        public readonly array $metadata = [],
    ) {
    }
}
