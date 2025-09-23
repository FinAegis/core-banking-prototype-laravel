<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionEncrypted extends ShouldBeStored
{
    public function __construct(
        public readonly string $securityId,
        public readonly string $transactionId,
        public readonly string $agentId,
        public readonly string $encryptedData,
        public readonly string $encryptionMethod,
        public readonly string $keyId,
        public readonly Carbon $timestamp,
        public readonly array $metadata = []
    ) {
    }
}
