<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use App\Domain\AgentProtocol\Enums\KycVerificationLevel;
use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentKycInitiated extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $agentDid,
        public readonly KycVerificationLevel $verificationLevel,
        public readonly array $requiredDocuments,
        public readonly Carbon $initiatedAt
    ) {
    }
}
