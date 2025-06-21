<?php

declare(strict_types=1);

namespace App\Domain\Custodian\ValueObjects;

use Carbon\Carbon;

final class TransactionReceipt
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $fromAccount = null,
        public readonly ?string $toAccount = null,
        public readonly ?string $assetCode = null,
        public readonly ?int $amount = null,
        public readonly ?int $fee = null,
        public readonly ?string $reference = null,
        public readonly ?Carbon $createdAt = null,
        public readonly ?Carbon $completedAt = null,
        public readonly ?array $metadata = [],
        public readonly ?string $failureReason = null
    ) {}

    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'success', 'settled']);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing', 'initiated']);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'rejected', 'cancelled']);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'from_account' => $this->fromAccount,
            'to_account' => $this->toAccount,
            'asset_code' => $this->assetCode,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'reference' => $this->reference,
            'created_at' => $this->createdAt?->toISOString(),
            'completed_at' => $this->completedAt?->toISOString(),
            'metadata' => $this->metadata,
            'failure_reason' => $this->failureReason,
        ];
    }
}