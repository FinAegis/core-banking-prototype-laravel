<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentVerified extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly Carbon $verifiedAt,
        public readonly string $verificationMethod,
        public readonly string $verificationLevel
    ) {
    }
}
