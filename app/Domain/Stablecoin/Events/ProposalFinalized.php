<?php

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProposalFinalized extends ShouldBeStored
{
    public function __construct(
        public readonly string $proposalId,
        public readonly string $result,
        public readonly string $totalVotes,
        public readonly array $votesSummary,
        public readonly bool $quorumReached,
        public readonly string $approvalRate
    ) {}
}