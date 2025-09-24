<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use App\Domain\AgentProtocol\Enums\KycVerificationLevel;
use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentKycVerified extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly KycVerificationLevel $verificationLevel,
        public readonly array $verificationResults,
        public readonly int $riskScore,
        public readonly Carbon $expiresAt,
        public readonly array $complianceFlags,
        public readonly Carbon $verifiedAt
    ) {
    }
}
