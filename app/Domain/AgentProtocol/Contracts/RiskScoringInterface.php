<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Contracts;

/**
 * Interface for risk scoring services within the Agent Protocol.
 *
 * Implementations assess various risk factors to calculate
 * overall risk scores for agent transactions and operations.
 */
interface RiskScoringInterface
{
    /**
     * Calculate the overall risk score for an agent transaction.
     *
     * @param string $agentId The agent performing the action
     * @param float $amount The transaction amount
     * @param array<string, mixed> $context Additional context for risk assessment
     * @return array{
     *     score: int,
     *     level: string,
     *     factors: array<string, array{score: int, weight: int, reason: string}>,
     *     passed: bool,
     *     timestamp: string
     * } Risk assessment result
     */
    public function calculateRisk(string $agentId, float $amount, array $context = []): array;

    /**
     * Get the risk level label for a given score.
     *
     * @param int $score Risk score (0-100)
     * @return string Risk level (low, medium, high, critical)
     */
    public function getRiskLevel(int $score): string;

    /**
     * Check if a risk score is within acceptable thresholds.
     *
     * @param int $score The risk score to evaluate
     * @param string $operationType Type of operation (e.g., 'payment', 'withdrawal', 'registration')
     * @return bool True if the risk is acceptable for the operation type
     */
    public function isAcceptableRisk(int $score, string $operationType = 'default'): bool;

    /**
     * Get the configured risk weights for scoring factors.
     *
     * @return array<string, int> Map of factor names to their weights
     */
    public function getRiskWeights(): array;
}
