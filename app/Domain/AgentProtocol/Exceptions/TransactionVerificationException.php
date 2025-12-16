<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Exceptions;

use Throwable;

/**
 * Exception thrown when transaction verification fails.
 *
 * This exception is used when a transaction cannot be verified
 * due to security, compliance, or validation issues.
 */
class TransactionVerificationException extends AgentProtocolException
{
    private string $transactionId;

    private string $reason;

    /** @var array<string, mixed> */
    private array $failedChecks;

    /**
     * Create a new transaction verification exception.
     *
     * @param string $transactionId The transaction identifier
     * @param string $reason The reason for verification failure
     * @param array<string, mixed> $failedChecks Details of failed checks
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $transactionId,
        string $reason,
        array $failedChecks = [],
        ?Throwable $previous = null
    ) {
        $this->transactionId = $transactionId;
        $this->reason = $reason;
        $this->failedChecks = $failedChecks;

        parent::__construct(
            "Transaction verification failed for {$transactionId}: {$reason}",
            422,
            $previous
        );
    }

    /**
     * Create exception for signature verification failure.
     *
     * @param string $transactionId The transaction identifier
     * @return self
     */
    public static function signatureInvalid(string $transactionId): self
    {
        return new self(
            $transactionId,
            'Invalid digital signature',
            ['signature' => ['valid' => false]]
        );
    }

    /**
     * Create exception for fraud detection failure.
     *
     * @param string $transactionId The transaction identifier
     * @param float $riskScore The fraud risk score
     * @return self
     */
    public static function fraudDetected(string $transactionId, float $riskScore): self
    {
        return new self(
            $transactionId,
            'Transaction flagged for potential fraud',
            ['fraud' => ['risk_score' => $riskScore, 'threshold_exceeded' => true]]
        );
    }

    /**
     * Create exception for compliance failure.
     *
     * @param string $transactionId The transaction identifier
     * @param array<string, mixed> $violations The compliance violations
     * @return self
     */
    public static function complianceViolation(string $transactionId, array $violations): self
    {
        return new self(
            $transactionId,
            'Transaction failed compliance checks',
            ['compliance' => $violations]
        );
    }

    /**
     * Create exception for velocity limit exceeded.
     *
     * @param string $transactionId The transaction identifier
     * @param string $limitType The type of limit exceeded
     * @return self
     */
    public static function velocityLimitExceeded(string $transactionId, string $limitType): self
    {
        return new self(
            $transactionId,
            "Velocity limit exceeded: {$limitType}",
            ['velocity' => ['limit_type' => $limitType, 'exceeded' => true]]
        );
    }

    /**
     * Get the transaction ID.
     *
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * Get the failure reason.
     *
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * Get the failed checks.
     *
     * @return array<string, mixed>
     */
    public function getFailedChecks(): array
    {
        return $this->failedChecks;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorType(): string
    {
        return 'transaction_verification_failed';
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'reason'         => $this->reason,
            'failed_checks'  => $this->failedChecks,
        ];
    }
}
