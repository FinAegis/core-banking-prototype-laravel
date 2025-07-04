<?php

namespace App\Domain\Wallet\ValueObjects;

class SignedTransaction
{
    public function __construct(
        public readonly string $rawTransaction,
        public readonly string $hash,
        public readonly TransactionData $transactionData
    ) {}
    
    public function toArray(): array
    {
        return [
            'raw_transaction' => $this->rawTransaction,
            'hash' => $this->hash,
            'transaction_data' => $this->transactionData->toArray(),
        ];
    }
}