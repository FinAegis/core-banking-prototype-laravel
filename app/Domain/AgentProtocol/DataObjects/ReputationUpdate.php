<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\DataObjects;

class ReputationUpdate
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $transactionId,
        public readonly string $type, // transaction, dispute, boost, decay
        public readonly float $previousScore,
        public readonly float $newScore,
        public readonly float $scoreChange,
        public readonly string $reason,
        public readonly array $metadata = []
    ) {
    }

    public static function fromTransaction(
        string $agentId,
        string $transactionId,
        float $previousScore,
        float $newScore,
        string $outcome,
        float $value
    ): self {
        return new self(
            agentId: $agentId,
            transactionId: $transactionId,
            type: 'transaction',
            previousScore: $previousScore,
            newScore: $newScore,
            scoreChange: $newScore - $previousScore,
            reason: "Transaction {$outcome}",
            metadata: [
                'outcome' => $outcome,
                'value'   => $value,
            ]
        );
    }

    public static function fromDispute(
        string $agentId,
        string $disputeId,
        float $previousScore,
        float $newScore,
        string $severity,
        string $reason
    ): self {
        return new self(
            agentId: $agentId,
            transactionId: $disputeId,
            type: 'dispute',
            previousScore: $previousScore,
            newScore: $newScore,
            scoreChange: $newScore - $previousScore,
            reason: $reason,
            metadata: [
                'severity'   => $severity,
                'dispute_id' => $disputeId,
            ]
        );
    }

    public function toArray(): array
    {
        return [
            'agent_id'       => $this->agentId,
            'transaction_id' => $this->transactionId,
            'type'           => $this->type,
            'previous_score' => $this->previousScore,
            'new_score'      => $this->newScore,
            'score_change'   => $this->scoreChange,
            'reason'         => $this->reason,
            'metadata'       => $this->metadata,
        ];
    }
}
