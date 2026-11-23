<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use RuntimeException;

/**
 * Thrown when a Solana JSON-RPC call returns an error envelope or the HTTP
 * transport itself fails. Wraps the upstream RPC error code so dispatchers
 * can decide whether to retry, mark the send failed, or surface to the user.
 */
class SolanaRpcException extends RuntimeException
{
    private int $rpcCode = 0;

    private bool $outcomeUnknown = false;

    /**
     * @param bool $outcomeUnknown the call failed in a way that lets the node
     *                             have processed it (a timeout or a server error)
     */
    public static function fromRpcError(int $code, string $message, bool $outcomeUnknown = false): self
    {
        $exception = new self("Solana RPC error {$code}: {$message}");
        $exception->rpcCode = $code;
        $exception->outcomeUnknown = $outcomeUnknown;

        return $exception;
    }

    /**
     * Whether the node may still have processed the call. For sendTransaction
     * this means the transaction may land; it is not a rejection.
     */
    public function isOutcomeUnknown(): bool
    {
        return $this->outcomeUnknown;
    }

    public function getRpcCode(): int
    {
        return $this->rpcCode;
    }
}
