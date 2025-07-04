<?php

namespace App\Domain\Stablecoin\Workflows\Data;

class ReserveRebalanceData
{
    public function __construct(
        public readonly string $poolId,
        public readonly array $targetAllocations,
        public readonly string $executedBy,
        public readonly float $maxSlippage = 0.02
    ) {}
    
    public function toArray(): array
    {
        return [
            'pool_id' => $this->poolId,
            'target_allocations' => $this->targetAllocations,
            'executed_by' => $this->executedBy,
            'max_slippage' => $this->maxSlippage,
        ];
    }
}