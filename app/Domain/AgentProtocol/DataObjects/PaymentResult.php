<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\DataObjects;

use Carbon\Carbon;

/**
 * Represents the result of a payment operation.
 */
class PaymentResult
{
    public ?string $paymentId = null;

    public ?string $escrowId = null;

    public ?float $fees = null;

    public ?string $errorMessage = null;

    public ?string $lastError = null;

    public ?Carbon $completedAt = null;

    public ?Carbon $failedAt = null;

    public ?array $splitResults = null;

    public float $amount = 0;

    public function __construct(
        public string $transactionId,
        public string $status,
        public Carbon $timestamp
    ) {
    }

    /**
     * Check if the payment was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the payment failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the payment is still processing.
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Get the net amount after fees.
     */
    public function getNetAmount(): float
    {
        return $this->amount - ($this->fees ?? 0);
    }

    /**
     * Convert to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'payment_id'     => $this->paymentId,
            'escrow_id'      => $this->escrowId,
            'status'         => $this->status,
            'amount'         => $this->amount,
            'fees'           => $this->fees,
            'net_amount'     => $this->getNetAmount(),
            'error_message'  => $this->errorMessage,
            'last_error'     => $this->lastError,
            'timestamp'      => $this->timestamp->toIso8601String(),
            'completed_at'   => $this->completedAt?->toIso8601String(),
            'failed_at'      => $this->failedAt?->toIso8601String(),
            'split_results'  => $this->splitResults,
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        $result = new self(
            transactionId: $data['transaction_id'],
            status: $data['status'],
            timestamp: Carbon::parse($data['timestamp'])
        );

        $result->paymentId = $data['payment_id'] ?? null;
        $result->escrowId = $data['escrow_id'] ?? null;
        $result->amount = $data['amount'] ?? 0;
        $result->fees = $data['fees'] ?? null;
        $result->errorMessage = $data['error_message'] ?? null;
        $result->lastError = $data['last_error'] ?? null;
        $result->completedAt = isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : null;
        $result->failedAt = isset($data['failed_at']) ? Carbon::parse($data['failed_at']) : null;
        $result->splitResults = $data['split_results'] ?? null;

        return $result;
    }
}
