<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Interface for compliance checks used by external domains.
 *
 * This interface enables domain decoupling by allowing domains like
 * Exchange, Lending, AgentProtocol, etc. to perform compliance checks
 * without directly depending on the Compliance domain implementation.
 *
 * @see \App\Domain\Compliance\Services\ComplianceService for implementation
 */
interface ComplianceCheckInterface
{
    /**
     * KYC verification status levels.
     */
    public const KYC_NONE = 'none';
    public const KYC_BASIC = 'basic';           // $1,000/day limit
    public const KYC_ENHANCED = 'enhanced';     // $10,000/day limit
    public const KYC_FULL = 'full';             // Unlimited

    /**
     * Get the KYC verification status for a user.
     *
     * @param string $userId User UUID
     * @return array{
     *     level: string,
     *     verified: bool,
     *     daily_limit: string,
     *     monthly_limit: string,
     *     verified_at: string|null,
     *     expires_at: string|null
     * }
     */
    public function getKYCStatus(string $userId): array;

    /**
     * Check if a user has the minimum required KYC level.
     *
     * @param string $userId User UUID
     * @param string $requiredLevel Minimum required level (basic, enhanced, full)
     * @return bool True if user meets the requirement
     */
    public function hasMinimumKYCLevel(string $userId, string $requiredLevel): bool;

    /**
     * Validate a transaction against compliance rules.
     *
     * @param array{
     *     user_id: string,
     *     type: string,
     *     amount: string,
     *     currency: string,
     *     counterparty_id?: string,
     *     metadata?: array<string, mixed>
     * } $transaction Transaction details to validate
     * @return array{
     *     approved: bool,
     *     reason: string|null,
     *     alerts: array<string>,
     *     requires_review: bool
     * }
     */
    public function validateTransaction(array $transaction): array;

    /**
     * Check transaction limits for a user.
     *
     * @param string $userId User UUID
     * @param string $amount Transaction amount (as string for precision)
     * @param string $currency Currency code
     * @return array{
     *     within_limits: bool,
     *     daily_used: string,
     *     daily_remaining: string,
     *     monthly_used: string,
     *     monthly_remaining: string
     * }
     */
    public function checkTransactionLimits(string $userId, string $amount, string $currency): array;

    /**
     * Perform AML (Anti-Money Laundering) screening.
     *
     * @param string $userId User UUID
     * @return array{
     *     passed: bool,
     *     risk_score: float,
     *     flags: array<string>,
     *     sanctions_match: bool,
     *     pep_match: bool,
     *     screened_at: string
     * }
     */
    public function screenAML(string $userId): array;

    /**
     * Check if a user is blocked from transactions.
     *
     * @param string $userId User UUID
     * @return array{
     *     blocked: bool,
     *     reason: string|null,
     *     blocked_at: string|null,
     *     unblock_at: string|null
     * }
     */
    public function isUserBlocked(string $userId): array;

    /**
     * Report a suspicious activity for review.
     *
     * @param array{
     *     user_id: string,
     *     type: string,
     *     description: string,
     *     transaction_id?: string,
     *     metadata?: array<string, mixed>
     * } $report Suspicious activity details
     * @return string Alert ID
     */
    public function reportSuspiciousActivity(array $report): string;
}
