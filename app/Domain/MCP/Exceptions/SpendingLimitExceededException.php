<?php

declare(strict_types=1);

namespace App\Domain\MCP\Exceptions;

use RuntimeException;

/**
 * Thrown by SpendingEnforcedToolCallSaga when the per-token daily limit
 * cannot accommodate the requested payment. Carries the structured data
 * (error code, remaining minor amount, window reset time) the JSON-RPC
 * dispatcher echoes back as a -32003 SPENDING_LIMIT_EXCEEDED response.
 */
final class SpendingLimitExceededException extends RuntimeException
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly array $data,
    ) {
        parent::__construct('Spending limit exceeded');
    }
}
