<?php

namespace App\Domain\Stablecoin\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProposalCancelled extends ShouldBeStored
{
    public function __construct(
        public readonly string $proposalId,
        public readonly string $reason,
        public readonly string $cancelledBy,
        public readonly Carbon $timestamp
    ) {}
}