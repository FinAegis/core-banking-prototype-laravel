<?php

declare(strict_types=1);

namespace App\Domain\Banking\Models;

use Carbon\Carbon;

class BankBalance
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $currency,
        public readonly float $available,
        public readonly float $current,
        public readonly float $pending,
        public readonly float $reserved,
        public readonly Carbon $asOf,
        public readonly array $metadata = [],
    ) {}
    
    /**
     * Get total balance (current + pending)
     */
    public function getTotal(): float
    {
        return $this->current + $this->pending;
    }
    
    /**
     * Get usable balance (available - reserved)
     */
    public function getUsable(): float
    {
        return $this->available - $this->reserved;
    }
    
    /**
     * Check if balance is sufficient for an amount
     */
    public function hasSufficientFunds(float $amount): bool
    {
        return $this->getUsable() >= $amount;
    }
    
    /**
     * Format balance for display
     */
    public function format(): string
    {
        return sprintf(
            '%s %.2f (Available: %.2f)',
            $this->currency,
            $this->current / 100,
            $this->available / 100
        );
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'currency' => $this->currency,
            'available' => $this->available,
            'current' => $this->current,
            'pending' => $this->pending,
            'reserved' => $this->reserved,
            'as_of' => $this->asOf->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
    
    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accountId: $data['account_id'],
            currency: $data['currency'],
            available: (float) $data['available'],
            current: (float) $data['current'],
            pending: (float) $data['pending'],
            reserved: (float) $data['reserved'],
            asOf: Carbon::parse($data['as_of']),
            metadata: $data['metadata'] ?? [],
        );
    }
}