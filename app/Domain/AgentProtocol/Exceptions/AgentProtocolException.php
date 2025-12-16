<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Exceptions;

use DomainException;
use Throwable;

/**
 * Base exception for all Agent Protocol domain exceptions.
 *
 * This abstract class serves as the foundation for all domain-specific
 * exceptions in the Agent Protocol bounded context.
 */
abstract class AgentProtocolException extends DomainException
{
    /**
     * Create a new exception instance.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the error type for API responses.
     *
     * @return string
     */
    abstract public function getErrorType(): string;

    /**
     * Get additional context for logging.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [];
    }
}
