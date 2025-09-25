<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing an agent's reputation score.
 * Immutable by design.
 */
final class ReputationScore
{
    private float $score;

    private string $trustLevel;

    private int $totalTransactions;

    private int $successfulTransactions;

    public function __construct(
        float $score,
        string $trustLevel,
        int $totalTransactions = 0,
        int $successfulTransactions = 0
    ) {
        $this->validateScore($score);
        $this->validateTrustLevel($trustLevel);
        $this->validateTransactions($totalTransactions, $successfulTransactions);

        $this->score = $score;
        $this->trustLevel = $trustLevel;
        $this->totalTransactions = $totalTransactions;
        $this->successfulTransactions = $successfulTransactions;
    }

    private function validateScore(float $score): void
    {
        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException('Reputation score must be between 0 and 100');
        }
    }

    private function validateTrustLevel(string $trustLevel): void
    {
        $validLevels = ['untrusted', 'low', 'medium', 'high', 'verified'];
        if (! in_array($trustLevel, $validLevels, true)) {
            throw new InvalidArgumentException('Invalid trust level: ' . $trustLevel);
        }
    }

    private function validateTransactions(int $total, int $successful): void
    {
        if ($total < 0 || $successful < 0) {
            throw new InvalidArgumentException('Transaction counts cannot be negative');
        }

        if ($successful > $total) {
            throw new InvalidArgumentException('Successful transactions cannot exceed total transactions');
        }
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getTrustLevel(): string
    {
        return $this->trustLevel;
    }

    public function getTotalTransactions(): int
    {
        return $this->totalTransactions;
    }

    public function getSuccessfulTransactions(): int
    {
        return $this->successfulTransactions;
    }

    public function getSuccessRate(): float
    {
        if ($this->totalTransactions === 0) {
            return 0.0;
        }

        return ($this->successfulTransactions / $this->totalTransactions) * 100;
    }

    /**
     * Calculate new score after a successful transaction.
     */
    public function withSuccessfulTransaction(): self
    {
        $newTotal = $this->totalTransactions + 1;
        $newSuccessful = $this->successfulTransactions + 1;
        $newScore = min(100, $this->score + 0.5); // Increase by 0.5 points

        return new self(
            $newScore,
            $this->calculateTrustLevel($newScore),
            $newTotal,
            $newSuccessful
        );
    }

    /**
     * Calculate new score after a failed transaction.
     */
    public function withFailedTransaction(): self
    {
        $newTotal = $this->totalTransactions + 1;
        $newScore = max(0, $this->score - 2.0); // Decrease by 2 points

        return new self(
            $newScore,
            $this->calculateTrustLevel($newScore),
            $newTotal,
            $this->successfulTransactions
        );
    }

    /**
     * Apply decay over time.
     */
    public function withDecay(float $decayFactor = 0.99): self
    {
        $newScore = $this->score * $decayFactor;

        return new self(
            $newScore,
            $this->calculateTrustLevel($newScore),
            $this->totalTransactions,
            $this->successfulTransactions
        );
    }

    /**
     * Calculate trust level based on score.
     */
    private function calculateTrustLevel(float $score): string
    {
        if ($score >= 90) {
            return 'verified';
        } elseif ($score >= 70) {
            return 'high';
        } elseif ($score >= 50) {
            return 'medium';
        } elseif ($score >= 30) {
            return 'low';
        } else {
            return 'untrusted';
        }
    }

    public function isHighTrust(): bool
    {
        return in_array($this->trustLevel, ['high', 'verified'], true);
    }

    public function equals(self $other): bool
    {
        return $this->score === $other->score &&
               $this->trustLevel === $other->trustLevel &&
               $this->totalTransactions === $other->totalTransactions &&
               $this->successfulTransactions === $other->successfulTransactions;
    }

    public function toString(): string
    {
        return sprintf(
            'Score: %.2f (%s) - %d/%d transactions',
            $this->score,
            $this->trustLevel,
            $this->successfulTransactions,
            $this->totalTransactions
        );
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Create from array for reconstitution.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (float) ($data['score'] ?? 50),
            $data['trust_level'] ?? 'medium',
            (int) ($data['total_transactions'] ?? 0),
            (int) ($data['successful_transactions'] ?? 0)
        );
    }

    /**
     * Convert to array for persistence.
     */
    public function toArray(): array
    {
        return [
            'score'                   => $this->score,
            'trust_level'             => $this->trustLevel,
            'total_transactions'      => $this->totalTransactions,
            'successful_transactions' => $this->successfulTransactions,
            'success_rate'            => $this->getSuccessRate(),
        ];
    }
}
