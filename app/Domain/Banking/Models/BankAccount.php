<?php

declare(strict_types=1);

namespace App\Domain\Banking\Models;

use Carbon\Carbon;

class BankAccount
{
    public function __construct(
        public readonly string $id,
        public readonly string $bankCode,
        public readonly string $accountNumber,
        public readonly string $iban,
        public readonly string $swift,
        public readonly string $currency,
        public readonly string $accountType,
        public readonly string $status,
        public readonly ?string $holderName,
        public readonly ?string $holderAddress,
        public readonly array $metadata,
        public readonly Carbon $createdAt,
        public readonly Carbon $updatedAt,
        public readonly ?Carbon $closedAt = null,
    ) {}
    
    /**
     * Check if account is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    
    /**
     * Check if account is closed
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed' || $this->closedAt !== null;
    }
    
    /**
     * Check if account supports a specific currency
     */
    public function supportsCurrency(string $currency): bool
    {
        if (!isset($this->metadata['supported_currencies'])) {
            return $this->currency === $currency;
        }
        
        return in_array($currency, $this->metadata['supported_currencies']);
    }
    
    /**
     * Get account label for display
     */
    public function getLabel(): string
    {
        return sprintf(
            '%s (...%s) - %s',
            $this->metadata['nickname'] ?? $this->accountType,
            substr($this->accountNumber, -4),
            $this->currency
        );
    }
    
    /**
     * Convert to array for storage
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'bank_code' => $this->bankCode,
            'account_number' => $this->accountNumber,
            'iban' => $this->iban,
            'swift' => $this->swift,
            'currency' => $this->currency,
            'account_type' => $this->accountType,
            'status' => $this->status,
            'holder_name' => $this->holderName,
            'holder_address' => $this->holderAddress,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
            'closed_at' => $this->closedAt?->toIso8601String(),
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
            accountNumber: $data['account_number'],
            iban: $data['iban'],
            swift: $data['swift'],
            currency: $data['currency'],
            accountType: $data['account_type'],
            status: $data['status'],
            holderName: $data['holder_name'] ?? null,
            holderAddress: $data['holder_address'] ?? null,
            metadata: $data['metadata'] ?? [],
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at']),
            closedAt: isset($data['closed_at']) ? Carbon::parse($data['closed_at']) : null,
        );
    }
}