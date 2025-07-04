<?php

namespace App\Domain\Stablecoin\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProposalVoteCast extends ShouldBeStored
{
    public function __construct(
        public readonly string $proposalId,
        public readonly string $voter,
        public readonly string $choice,
        public readonly string $votingPower,
        public readonly string $reason,
        public readonly Carbon $timestamp
    ) {}
}