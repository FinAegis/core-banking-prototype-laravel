<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionVerified extends ShouldBeStored
{
    public function __construct(
        public readonly string $securityId,
        public readonly string $transactionId,
        public readonly string $agentId,
        public readonly bool $isValid,
        public readonly bool $signatureValid,
        public readonly bool $encryptionValid,
        public readonly array $verificationDetails,
        public readonly Carbon $timestamp,
        public readonly array $metadata = []
    ) {
    }
}
