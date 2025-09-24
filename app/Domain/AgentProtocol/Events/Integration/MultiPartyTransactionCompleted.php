<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MultiPartyTransactionCompleted extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly array $senders,
        public readonly array $recipients,
        public readonly float $totalAmount,
        public readonly array $transactions
    ) {
    }
}
