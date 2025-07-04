<?php

namespace App\Domain\Wallet\ValueObjects;

class TransactionData
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $value,
        public readonly string $chain,
        public readonly ?string $data = null,
        public readonly ?string $gasLimit = null,
        public readonly ?string $gasPrice = null,
        public readonly ?string $maxFeePerGas = null,
        public readonly ?string $maxPriorityFeePerGas = null,
        public readonly ?int $nonce = null,
        public readonly ?string $hash = null,
        public readonly ?int $blockNumber = null,
        public readonly ?string $status = null,
        public readonly array $metadata = []
    ) {}
    
    public function toArray(): array
    {
        return array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'value' => $this->value,
            'chain' => $this->chain,
            'data' => $this->data,
            'gas_limit' => $this->gasLimit,
            'gas_price' => $this->gasPrice,
            'max_fee_per_gas' => $this->maxFeePerGas,
            'max_priority_fee_per_gas' => $this->maxPriorityFeePerGas,
            'nonce' => $this->nonce,
            'hash' => $this->hash,
            'block_number' => $this->blockNumber,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ], fn($value) => $value !== null);
    }
    
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed' && $this->blockNumber !== null;
    }
}