<?php

namespace App\Domain\Cgo\DataObjects;

use Spatie\LaravelData\Data;

class RefundRequest extends Data
{
    public function __construct(
        public string $investmentId,
        public string $userId,
        public int $amount,
        public string $currency,
        public string $reason,
        public ?string $reasonDetails,
        public string $initiatedBy,
        public bool $autoApproved = false,
        public array $metadata = []
    ) {}
    
    public function getInvestmentId(): string
    {
        return $this->investmentId;
    }
    
    public function getUserId(): string
    {
        return $this->userId;
    }
    
    public function getAmount(): int
    {
        return $this->amount;
    }
    
    public function getCurrency(): string
    {
        return $this->currency;
    }
    
    public function getReason(): string
    {
        return $this->reason;
    }
    
    public function getReasonDetails(): ?string
    {
        return $this->reasonDetails;
    }
    
    public function getInitiatedBy(): string
    {
        return $this->initiatedBy;
    }
    
    public function isAutoApproved(): bool
    {
        return $this->autoApproved;
    }
    
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}