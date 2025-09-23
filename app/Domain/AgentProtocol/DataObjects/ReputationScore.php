<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\DataObjects;

use InvalidArgumentException;

class ReputationScore
{
    public function __construct(
        public readonly string $agentId,
        public readonly float $score,
        public readonly string $trustLevel,
        public readonly int $totalTransactions,
        public readonly int $successfulTransactions,
        public readonly int $failedTransactions,
        public readonly int $disputedTransactions,
        public readonly float $successRate,
        public readonly ?string $lastActivityAt = null,
        public readonly array $metadata = []
    ) {
        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException('Score must be between 0 and 100');
        }

        if (! in_array($trustLevel, ['untrusted', 'low', 'neutral', 'high', 'trusted'])) {
            throw new InvalidArgumentException('Invalid trust level');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            agentId: $data['agent_id'],
            score: (float) $data['score'],
            trustLevel: $data['trust_level'],
            totalTransactions: (int) $data['total_transactions'],
            successfulTransactions: (int) $data['successful_transactions'],
            failedTransactions: (int) $data['failed_transactions'],
            disputedTransactions: (int) $data['disputed_transactions'],
            successRate: (float) $data['success_rate'],
            lastActivityAt: $data['last_activity_at'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'agent_id'                => $this->agentId,
            'score'                   => $this->score,
            'trust_level'             => $this->trustLevel,
            'total_transactions'      => $this->totalTransactions,
            'successful_transactions' => $this->successfulTransactions,
            'failed_transactions'     => $this->failedTransactions,
            'disputed_transactions'   => $this->disputedTransactions,
            'success_rate'            => $this->successRate,
            'last_activity_at'        => $this->lastActivityAt,
            'metadata'                => $this->metadata,
        ];
    }

    public function isHighRisk(): bool
    {
        return $this->trustLevel === 'untrusted' || $this->score < 20;
    }

    public function requiresManualReview(): bool
    {
        return $this->trustLevel === 'low' || ($this->disputedTransactions > 5 && $this->successRate < 50);
    }
}
