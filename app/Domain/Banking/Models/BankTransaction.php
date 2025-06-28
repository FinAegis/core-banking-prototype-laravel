<?php

declare(strict_types=1);

namespace App\Domain\Banking\Models;

use Carbon\Carbon;

class BankTransaction
{
    public function __construct(
        public readonly string $id,
        public readonly string $bankCode,
        public readonly string $accountId,
        public readonly string $type, // debit, credit
        public readonly string $category, // transfer, fee, interest, etc.
        public readonly float $amount,
        public readonly string $currency,
        public readonly float $balanceAfter,
        public readonly ?string $reference,
        public readonly ?string $description,
        public readonly ?string $counterpartyName,
        public readonly ?string $counterpartyAccount,
        public readonly ?string $counterpartyBank,
        public readonly Carbon $transactionDate,
        public readonly Carbon $valueDate,
        public readonly Carbon $bookingDate,
        public readonly string $status,
        public readonly array $metadata = [],
    ) {}
    
    /**
     * Check if transaction is a debit
     */
    public function isDebit(): bool
    {
        return $this->type === 'debit' || $this->amount < 0;
    }
    
    /**
     * Check if transaction is a credit
     */
    public function isCredit(): bool
    {
        return $this->type === 'credit' || $this->amount > 0;
    }
    
    /**
     * Get absolute amount
     */
    public function getAbsoluteAmount(): float
    {
        return abs($this->amount);
    }
    
    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    
    /**
     * Check if transaction is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' || $this->status === 'booked';
    }
    
    /**
     * Format for display
     */
    public function format(): string
    {
        $sign = $this->isDebit() ? '-' : '+';
        return sprintf(
            '%s %s%.2f %s - %s',
            $this->transactionDate->format('Y-m-d'),
            $sign,
            $this->getAbsoluteAmount() / 100,
            $this->currency,
            $this->description ?? $this->category
        );
    }
    
    /**
     * Get transaction direction
     */
    public function getDirection(): string
    {
        return $this->isDebit() ? 'outgoing' : 'incoming';
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'bank_code' => $this->bankCode,
            'account_id' => $this->accountId,
            'type' => $this->type,
            'category' => $this->category,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'balance_after' => $this->balanceAfter,
            'reference' => $this->reference,
            'description' => $this->description,
            'counterparty_name' => $this->counterpartyName,
            'counterparty_account' => $this->counterpartyAccount,
            'counterparty_bank' => $this->counterpartyBank,
            'transaction_date' => $this->transactionDate->toIso8601String(),
            'value_date' => $this->valueDate->toIso8601String(),
            'booking_date' => $this->bookingDate->toIso8601String(),
            'status' => $this->status,
            'metadata' => $this->metadata,
        ];
    }
    
    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            bankCode: $data['bank_code'],
            accountId: $data['account_id'],
            type: $data['type'],
            category: $data['category'],
            amount: (float) $data['amount'],
            currency: $data['currency'],
            balanceAfter: (float) $data['balance_after'],
            reference: $data['reference'] ?? null,
            description: $data['description'] ?? null,
            counterpartyName: $data['counterparty_name'] ?? null,
            counterpartyAccount: $data['counterparty_account'] ?? null,
            counterpartyBank: $data['counterparty_bank'] ?? null,
            transactionDate: Carbon::parse($data['transaction_date']),
            valueDate: Carbon::parse($data['value_date']),
            bookingDate: Carbon::parse($data['booking_date']),
            status: $data['status'],
            metadata: $data['metadata'] ?? [],
        );
    }
}