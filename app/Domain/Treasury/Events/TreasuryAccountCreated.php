<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TreasuryAccountCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $name,
        public readonly string $currency,
        public readonly string $accountType,
        public readonly float $initialBalance,
        public readonly array $metadata
    ) {
    }
}
