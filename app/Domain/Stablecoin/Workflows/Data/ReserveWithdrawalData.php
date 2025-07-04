<?php

namespace App\Domain\Stablecoin\Workflows\Data;

class ReserveWithdrawalData
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $asset,
        public readonly string $amount,
        public readonly string $custodianId,
        public readonly string $destinationAddress,
        public readonly string $reason,
        public readonly array $metadata = []
    ) {}
    
    public function toArray(): array
    {
        return [
            'pool_id' => $this->poolId,
            'asset' => $this->asset,
            'amount' => $this->amount,
            'custodian_id' => $this->custodianId,
            'destination_address' => $this->destinationAddress,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
        ];
    }
}