<?php

declare(strict_types=1);

namespace App\Domain\Banking\Models;

use Carbon\Carbon;

class BankTransfer
{
    public function __construct(
        public readonly string $id,
        public readonly string $bankCode,
        public readonly string $type, // SEPA, SWIFT, INTERNAL, etc.
        public readonly string $status,
        public readonly string $fromAccountId,
        public readonly string $toAccountId,
        public readonly string $toBankCode,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $reference,
        public readonly ?string $description,
        public readonly array $fees,
        public readonly array $exchangeRate,
        public readonly Carbon $createdAt,
        public readonly Carbon $updatedAt,
        public readonly ?Carbon $executedAt,
        public readonly ?Carbon $failedAt,
        public readonly ?string $failureReason,
        public readonly array $metadata = [],
    ) {}
    
    /**
     * Check if transfer is pending
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing', 'submitted']);
    }
    
    /**
     * Check if transfer is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' && $this->executedAt !== null;
    }
    
    /**
     * Check if transfer failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed' || $this->failedAt !== null;
    }
    
    /**
     * Check if transfer can be cancelled
     */
    public function isCancellable(): bool
    {
        return $this->isPending() && !in_array($this->status, ['submitted', 'processing']);
    }
    
    /**
     * Get total amount including fees
     */
    public function getTotalAmount(): float
    {
        $totalFees = array_sum(array_column($this->fees, 'amount'));
        return $this->amount + $totalFees;
    }
    
    /**
     * Get net amount after fees
     */
    public function getNetAmount(): float
    {
        if ($this->metadata['fee_mode'] === 'shared') {
            return $this->amount - (array_sum(array_column($this->fees, 'amount')) / 2);
        }
        
        return $this->metadata['fee_mode'] === 'sender' ? $this->amount : $this->amount - array_sum(array_column($this->fees, 'amount'));
    }
    
    /**
     * Get estimated arrival time
     */
    public function getEstimatedArrival(): ?Carbon
    {
        if ($this->isCompleted() || $this->isFailed()) {
            return null;
        }
        
        $estimatedHours = match($this->type) {
            'INTERNAL' => 0,
            'SEPA' => 24,
            'SEPA_INSTANT' => 0,
            'SWIFT' => 72,
            default => 48,
        };
        
        return $this->createdAt->copy()->addHours($estimatedHours);
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'bank_code' => $this->bankCode,
            'type' => $this->type,
            'status' => $this->status,
            'from_account_id' => $this->fromAccountId,
            'to_account_id' => $this->toAccountId,
            'to_bank_code' => $this->toBankCode,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'description' => $this->description,
            'fees' => $this->fees,
            'exchange_rate' => $this->exchangeRate,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
            'executed_at' => $this->executedAt?->toIso8601String(),
            'failed_at' => $this->failedAt?->toIso8601String(),
            'failure_reason' => $this->failureReason,
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
            type: $data['type'],
            status: $data['status'],
            fromAccountId: $data['from_account_id'],
            toAccountId: $data['to_account_id'],
            toBankCode: $data['to_bank_code'],
            amount: (float) $data['amount'],
            currency: $data['currency'],
            reference: $data['reference'] ?? null,
            description: $data['description'] ?? null,
            fees: $data['fees'] ?? [],
            exchangeRate: $data['exchange_rate'] ?? [],
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at']),
            executedAt: isset($data['executed_at']) ? Carbon::parse($data['executed_at']) : null,
            failedAt: isset($data['failed_at']) ? Carbon::parse($data['failed_at']) : null,
            failureReason: $data['failure_reason'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }
}