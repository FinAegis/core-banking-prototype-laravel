<?php

declare(strict_types=1);

namespace App\Domain\MCP\Exceptions;

use RuntimeException;

/**
 * Thrown by IdempotencyCache::remember() when a concurrent request holds the
 * in-flight lock for the same (token, tool, idempotency_key) tuple. Mapped by
 * JsonRpcRouter to JSON-RPC -32005 IDEMPOTENCY_KEY_IN_FLIGHT so the client
 * knows to retry — this is *not* the same condition as -32002
 * IDEMPOTENCY_KEY_REUSED (which means the args don't match).
 */
final class IdempotencyKeyInFlightException extends RuntimeException
{
}
