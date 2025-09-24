<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CrossDomainTransactionInitiated extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $fromAgentWallet,
        public readonly string $toAccount,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $metadata = []
    ) {
    }
}
