<?php

namespace App\Domain\Stablecoin\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProposalCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $proposalId,
        public readonly string $proposalType,
        public readonly string $title,
        public readonly string $description,
        public readonly array $parameters,
        public readonly string $proposer,
        public readonly Carbon $startTime,
        public readonly Carbon $endTime,
        public readonly string $quorumRequired,
        public readonly string $approvalThreshold
    ) {}
}