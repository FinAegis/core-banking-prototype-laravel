<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Exceptions;

use Throwable;

/**
 * Exception thrown when a wallet cannot be found.
 *
 * This exception is used when attempting to perform operations on a wallet
 * that does not exist or is not active.
 */
class WalletNotFoundException extends AgentProtocolException
{
    private string $identifier;

    private string $identifierType;

    /**
     * Create a new wallet not found exception.
     *
     * @param string $identifier The wallet or agent identifier
     * @param string $identifierType The type of identifier (wallet_id, agent_id)
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $identifier,
        string $identifierType = 'wallet_id',
        ?Throwable $previous = null
    ) {
        $this->identifier = $identifier;
        $this->identifierType = $identifierType;

        parent::__construct(
            "Wallet not found for {$identifierType}: {$identifier}",
            404,
            $previous
        );
    }

    /**
     * Create exception for agent without active wallet.
     *
     * @param string $agentId The agent identifier
     * @return self
     */
    public static function forAgent(string $agentId): self
    {
        return new self($agentId, 'agent_id');
    }

    /**
     * Create exception for wallet ID.
     *
     * @param string $walletId The wallet identifier
     * @return self
     */
    public static function forWalletId(string $walletId): self
    {
        return new self($walletId, 'wallet_id');
    }

    /**
     * Get the identifier.
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Get the identifier type.
     *
     * @return string
     */
    public function getIdentifierType(): string
    {
        return $this->identifierType;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorType(): string
    {
        return 'wallet_not_found';
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): array
    {
        return [
            'identifier'      => $this->identifier,
            'identifier_type' => $this->identifierType,
        ];
    }
}
