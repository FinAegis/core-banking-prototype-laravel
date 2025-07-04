<?php

namespace App\Domain\Wallet\ValueObjects;

class TransactionResult
{
    public function __construct(
        public readonly string $hash,
        public readonly string $status,
        public readonly ?int $blockNumber = null,
        public readonly ?string $gasUsed = null,
        public readonly ?string $effectiveGasPrice = null,
        public readonly ?array $logs = null,
        public readonly array $metadata = []
    ) {}
    
    public function toArray(): array
    {
        return array_filter([
            'hash' => $this->hash,
            'status' => $this->status,
            'block_number' => $this->blockNumber,
            'gas_used' => $this->gasUsed,
            'effective_gas_price' => $this->effectiveGasPrice,
            'logs' => $this->logs,
            'metadata' => $this->metadata,
        ], fn($value) => $value !== null);
    }
    
    public function isSuccess(): bool
    {
        return $this->status === 'success' || $this->status === '0x1';
    }
    
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    
    public function isFailed(): bool
    {
        return $this->status === 'failed' || $this->status === '0x0';
    }
}