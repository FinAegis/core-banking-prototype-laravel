<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RebalancingApprovalRequested extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly string $rebalanceId,
        public readonly string $approvalId,
        public readonly array $rebalancingPlan,
        public readonly string $reason,
        public readonly array $requiredApprovers,
        public readonly array $metadata
    ) {
    }
}
