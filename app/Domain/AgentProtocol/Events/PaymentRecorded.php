<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PaymentRecorded extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $paymentId,
        public readonly string $fromAgent,
        public readonly string $toAgent,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly float $fees,
        public readonly ?string $escrowId,
        public readonly array $metadata,
        public readonly string $recordedAt
    ) {
    }

    /**
     * Convert event to array.
     */
    public function toArray(): array
    {
        return [
            'transactionId' => $this->transactionId,
            'paymentId'     => $this->paymentId,
            'fromAgent'     => $this->fromAgent,
            'toAgent'       => $this->toAgent,
            'amount'        => $this->amount,
            'currency'      => $this->currency,
            'status'        => $this->status,
            'fees'          => $this->fees,
            'escrowId'      => $this->escrowId,
            'metadata'      => $this->metadata,
            'recordedAt'    => $this->recordedAt,
        ];
    }
}
