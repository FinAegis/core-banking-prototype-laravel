<?php

namespace App\Domain\Wallet\ValueObjects;

class GasEstimate
{
    public function __construct(
        public readonly string $gasLimit,
        public readonly string $gasPrice,
        public readonly string $maxFeePerGas,
        public readonly string $maxPriorityFeePerGas,
        public readonly string $estimatedCost,
        public readonly string $chain,
        public readonly array $metadata = []
    ) {}
    
    public function toArray(): array
    {
        return [
            'gas_limit' => $this->gasLimit,
            'gas_price' => $this->gasPrice,
            'max_fee_per_gas' => $this->maxFeePerGas,
            'max_priority_fee_per_gas' => $this->maxPriorityFeePerGas,
            'estimated_cost' => $this->estimatedCost,
            'chain' => $this->chain,
            'metadata' => $this->metadata,
        ];
    }
    
    public function getTotalCostInWei(): string
    {
        return bcmul($this->gasLimit, $this->gasPrice);
    }
}