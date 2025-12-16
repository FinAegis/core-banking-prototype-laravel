<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Exceptions;

use Throwable;

/**
 * Exception thrown when KYC status validation fails.
 *
 * This exception is used when an agent's KYC status does not meet
 * the requirements for a requested operation.
 */
class InvalidKycStatusException extends AgentProtocolException
{
    private string $agentId;

    private string $currentStatus;

    private string $requiredStatus;

    /**
     * Create a new invalid KYC status exception.
     *
     * @param string $agentId The agent identifier
     * @param string $currentStatus The agent's current KYC status
     * @param string $requiredStatus The required KYC status
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $agentId,
        string $currentStatus,
        string $requiredStatus,
        ?Throwable $previous = null
    ) {
        $this->agentId = $agentId;
        $this->currentStatus = $currentStatus;
        $this->requiredStatus = $requiredStatus;

        parent::__construct(
            "Agent {$agentId} has KYC status '{$currentStatus}' but requires '{$requiredStatus}'",
            403,
            $previous
        );
    }

    /**
     * Create exception for unverified agent.
     *
     * @param string $agentId The agent identifier
     * @param string $requiredStatus The required status
     * @return self
     */
    public static function unverified(string $agentId, string $requiredStatus = 'approved'): self
    {
        return new self($agentId, 'unverified', $requiredStatus);
    }

    /**
     * Create exception for pending verification.
     *
     * @param string $agentId The agent identifier
     * @return self
     */
    public static function pending(string $agentId): self
    {
        return new self($agentId, 'pending', 'approved');
    }

    /**
     * Get the agent ID.
     *
     * @return string
     */
    public function getAgentId(): string
    {
        return $this->agentId;
    }

    /**
     * Get the current KYC status.
     *
     * @return string
     */
    public function getCurrentStatus(): string
    {
        return $this->currentStatus;
    }

    /**
     * Get the required KYC status.
     *
     * @return string
     */
    public function getRequiredStatus(): string
    {
        return $this->requiredStatus;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorType(): string
    {
        return 'invalid_kyc_status';
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): array
    {
        return [
            'agent_id'        => $this->agentId,
            'current_status'  => $this->currentStatus,
            'required_status' => $this->requiredStatus,
        ];
    }
}
