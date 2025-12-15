<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentFundedFromMainAccount extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentWalletId,
        public readonly string $mainAccountUuid,
        public readonly float $amount,
        public readonly string $currency,
        public readonly float $convertedAmount,
        public readonly string $transactionId
    ) {
    }
}
