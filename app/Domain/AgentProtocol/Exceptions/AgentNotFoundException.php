<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Exceptions;

use Throwable;

/**
 * Exception thrown when an agent cannot be found.
 *
 * This exception is used when attempting to perform operations on an agent
 * that does not exist in the registry.
 */
class AgentNotFoundException extends AgentProtocolException
{
    private string $agentId;

    /**
     * Create a new agent not found exception.
     *
     * @param string $agentId The agent identifier (DID) that was not found
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(string $agentId, ?Throwable $previous = null)
    {
        $this->agentId = $agentId;

        parent::__construct(
            "Agent not found: {$agentId}",
            404,
            $previous
        );
    }

    /**
     * Create exception from DID.
     *
     * @param string $did The DID that was not found
     * @return self
     */
    public static function fromDid(string $did): self
    {
        return new self($did);
    }

    /**
     * Get the agent ID that was not found.
     *
     * @return string
     */
    public function getAgentId(): string
    {
        return $this->agentId;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorType(): string
    {
        return 'agent_not_found';
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): array
    {
        return [
            'agent_id' => $this->agentId,
        ];
    }
}
