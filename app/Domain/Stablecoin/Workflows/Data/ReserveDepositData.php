<?php

namespace App\Domain\Stablecoin\Workflows\Data;

class ReserveDepositData
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $asset,
        public readonly string $amount,
        public readonly string $custodianId,
        public readonly string $transactionHash,
        public readonly string $expectedAmount,
        public readonly array $metadata = []
    ) {}
    
    public function toArray(): array
    {
        return [
            'pool_id' => $this->poolId,
            'asset' => $this->asset,
            'amount' => $this->amount,
            'custodian_id' => $this->custodianId,
            'transaction_hash' => $this->transactionHash,
            'expected_amount' => $this->expectedAmount,
            'metadata' => $this->metadata,
        ];
    }
}