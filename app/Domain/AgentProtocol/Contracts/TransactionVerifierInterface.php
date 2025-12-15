<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Contracts;

/**
 * Interface for transaction verification services.
 *
 * Implementations of this interface are responsible for verifying
 * agent transactions through various security and compliance checks.
 */
interface TransactionVerifierInterface
{
    /**
     * Verify a transaction and return the verification result.
     *
     * @param string $transactionId The unique transaction identifier
     * @param array{
     *     sender_id: string,
     *     receiver_id: string,
     *     amount: float,
     *     currency: string,
     *     metadata?: array<string, mixed>
     * } $transactionData Transaction details to verify
     * @return array{
     *     verified: bool,
     *     verification_level: string,
     *     risk_score: int,
     *     checks_passed: array<string>,
     *     checks_failed: array<string>,
     *     timestamp: string
     * } Verification result
     */
    public function verify(string $transactionId, array $transactionData): array;

    /**
     * Get the verification level for a given risk score.
     *
     * @param int $riskScore The calculated risk score (0-100)
     * @return string The verification level (low, medium, high, critical)
     */
    public function getVerificationLevel(int $riskScore): string;

    /**
     * Calculate the risk score for a transaction.
     *
     * @param array{
     *     sender_id: string,
     *     receiver_id: string,
     *     amount: float,
     *     currency: string,
     *     metadata?: array<string, mixed>
     * } $transactionData Transaction details
     * @return int Risk score from 0 (lowest risk) to 100 (highest risk)
     */
    public function calculateRiskScore(array $transactionData): int;

    /**
     * Check if a transaction passes velocity limits.
     *
     * @param string $agentId The agent performing the transaction
     * @param float $amount The transaction amount
     * @return array{
     *     passed: bool,
     *     hourly_count: int,
     *     daily_count: int,
     *     daily_amount: float,
     *     limits: array<string, int|float>
     * } Velocity check result
     */
    public function checkVelocityLimits(string $agentId, float $amount): array;
}
