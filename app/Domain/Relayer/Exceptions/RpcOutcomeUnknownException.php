<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Exceptions;

/**
 * The call may or may not have taken effect.
 *
 * An attempt failed in a way that lets the server have processed it (a
 * timeout or a server error), and no later attempt settled the question.
 * For a write such as eth_sendUserOperation this is not a rejection.
 */
final class RpcOutcomeUnknownException extends RpcException
{
    public static function after(string $method, string $reason): self
    {
        return new self(
            message: "RPC connection failed: {$reason}",
            rpcMethod: $method,
        );
    }
}
