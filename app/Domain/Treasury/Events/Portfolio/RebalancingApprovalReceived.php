<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RebalancingApprovalReceived extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly string $rebalanceId,
        public readonly string $approvalId,
        public readonly bool $approved,
        public readonly string $approverId,
        public readonly ?string $comments,
        public readonly array $metadata
    ) {
    }
}
