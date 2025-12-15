<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentWithdrewToMainAccount extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentWalletId,
        public readonly string $mainAccountUuid,
        public readonly float $amount,
        public readonly string $currency,
        public readonly float $walletAmount,
        public readonly string $transactionId
    ) {
    }
}
