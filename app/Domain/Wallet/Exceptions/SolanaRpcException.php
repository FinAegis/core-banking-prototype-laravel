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

    public static function fromRpcError(int $code, string $message): self
    {
        $exception = new self("Solana RPC error {$code}: {$message}");
        $exception->rpcCode = $code;

        return $exception;
    }

    public function getRpcCode(): int
    {
        return $this->rpcCode;
    }
}
