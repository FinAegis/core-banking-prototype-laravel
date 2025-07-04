<?php

namespace App\Domain\Stablecoin\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProposalExecuted extends ShouldBeStored
{
    public function __construct(
        public readonly string $proposalId,
        public readonly string $executedBy,
        public readonly array $executionData,
        public readonly Carbon $timestamp
    ) {}
}