<?php

namespace App\Domain\Lending\DataObjects;

use App\Domain\Lending\Enums\CollateralType;
use App\Domain\Lending\Enums\CollateralStatus;
use Illuminate\Support\Carbon;

class Collateral
{
    public function __construct(
        public readonly string $collateralId,
        public readonly string $loanId,
        public readonly CollateralType $type,
        public readonly string $description,
        public readonly string $estimatedValue,
        public readonly string $currency,
        public readonly CollateralStatus $status,
        public readonly ?string $verificationDocumentId,
        public readonly ?Carbon $verifiedAt,
        public readonly ?string $verifiedBy,
        public readonly array $metadata = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            collateralId: $data['collateral_id'] ?? \Illuminate\Support\Str::uuid()->toString(),
            loanId: $data['loan_id'],
            type: CollateralType::from($data['type']),
            description: $data['description'],
            estimatedValue: $data['estimated_value'],
            currency: $data['currency'] ?? 'USD',
            status: CollateralStatus::from($data['status'] ?? CollateralStatus::PENDING_VERIFICATION->value),
            verificationDocumentId: $data['verification_document_id'] ?? null,
            verifiedAt: isset($data['verified_at']) ? Carbon::parse($data['verified_at']) : null,
            verifiedBy: $data['verified_by'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'collateral_id' => $this->collateralId,
            'loan_id' => $this->loanId,
            'type' => $this->type->value,
            'description' => $this->description,
            'estimated_value' => $this->estimatedValue,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'verification_document_id' => $this->verificationDocumentId,
            'verified_at' => $this->verifiedAt?->toIso8601String(),
            'verified_by' => $this->verifiedBy,
            'metadata' => $this->metadata
        ];
    }

    public function getLoanToValueRatio(string $loanAmount): float
    {
        $loanValue = (float) $loanAmount;
        $collateralValue = (float) $this->estimatedValue;
        
        if ($collateralValue <= 0) {
            return 1.0; // Maximum LTV if no collateral value
        }
        
        return $loanValue / $collateralValue;
    }

    public function isVerified(): bool
    {
        return $this->status === CollateralStatus::VERIFIED;
    }

    public function isPending(): bool
    {
        return $this->status === CollateralStatus::PENDING_VERIFICATION;
    }

    public function isRejected(): bool
    {
        return $this->status === CollateralStatus::REJECTED;
    }

    public function getRequiredLTV(): float
    {
        return $this->type->getRequiredLTV();
    }
}