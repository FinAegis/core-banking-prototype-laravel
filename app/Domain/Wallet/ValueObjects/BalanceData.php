<?php

namespace App\Domain\Wallet\ValueObjects;

class BalanceData
{
    public function __construct(
        public readonly string $address,
        public readonly string $balance,
        public readonly string $chain,
        public readonly string $symbol,
        public readonly int $decimals,
        public readonly ?string $pendingBalance = null,
        public readonly ?int $nonce = null,
        public readonly array $metadata = []
    ) {}
    
    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'balance' => $this->balance,
            'chain' => $this->chain,
            'symbol' => $this->symbol,
            'decimals' => $this->decimals,
            'pending_balance' => $this->pendingBalance,
            'nonce' => $this->nonce,
            'metadata' => $this->metadata,
        ];
    }
    
    public function getFormattedBalance(): string
    {
        $divisor = pow(10, $this->decimals);
        $balance = bcdiv($this->balance, (string)$divisor, $this->decimals);
        return rtrim(rtrim($balance, '0'), '.');
    }
}