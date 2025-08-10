<?php

declare(strict_types=1);

namespace App\Domain\AI\Events\Risk;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Credit Assessed Event.
 *
 * Emitted when credit risk assessment is completed.
 */
class CreditAssessedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $userId,
        public readonly array $assessment
    ) {
    }

    /**
     * Get event metadata.
     */
    public function eventMetadata(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id'         => $this->userId,
            'credit_score'    => $this->assessment['credit_score'] ?? 0,
            'risk_level'      => $this->assessment['risk_level'] ?? 'unknown',
            'approved'        => $this->assessment['approved'] ?? false,
            'dti_ratio'       => $this->assessment['dti_ratio'] ?? 0,
            'timestamp'       => now()->toIso8601String(),
        ];
    }
}
