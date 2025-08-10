<?php

declare(strict_types=1);

namespace App\Domain\AI\Events\Risk;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Fraud Assessed Event.
 *
 * Emitted when fraud risk assessment is completed.
 */
class FraudAssessedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $userId,
        public readonly ?string $transactionId,
        public readonly array $assessment
    ) {
    }

    /**
     * Get event metadata.
     */
    public function eventMetadata(): array
    {
        return [
            'conversation_id'   => $this->conversationId,
            'user_id'           => $this->userId,
            'transaction_id'    => $this->transactionId,
            'fraud_score'       => $this->assessment['fraud_score'] ?? 0,
            'risk_level'        => $this->assessment['risk_level'] ?? 'unknown',
            'block_transaction' => $this->assessment['block_transaction'] ?? false,
            'requires_2fa'      => $this->assessment['requires_2fa'] ?? false,
            'anomaly_count'     => count($this->assessment['anomalies']['detected'] ?? []),
            'timestamp'         => now()->toIso8601String(),
        ];
    }
}
