<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentKycDocumentsSubmitted extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly array $documents,
        public readonly Carbon $submittedAt
    ) {
    }
}
