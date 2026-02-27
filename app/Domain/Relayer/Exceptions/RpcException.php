<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Exceptions;

use RuntimeException;

/**
 * Structured exception for JSON-RPC errors from Ethereum nodes and bundlers.
 */
class RpcException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $rpcMethod,
        public readonly int $rpcErrorCode = 0,
        public readonly ?string $rpcErrorData = null,
    ) {
        parent::__construct($message, $rpcErrorCode);
    }

    public static function fromRpcError(string $method, array $error): self
    {
        return new self(
            message: $error['message'] ?? 'Unknown RPC error',
            rpcMethod: $method,
            rpcErrorCode: (int) ($error['code'] ?? 0),
            rpcErrorData: isset($error['data']) ? (is_string($error['data']) ? $error['data'] : json_encode($error['data'])) : null,
        );
    }

    public static function connectionFailed(string $method, string $reason): self
    {
        return new self(
            message: "RPC connection failed: {$reason}",
            rpcMethod: $method,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'rpc_method'     => $this->rpcMethod,
            'rpc_error_code' => $this->rpcErrorCode,
            'rpc_error_data' => $this->rpcErrorData,
        ];
    }
}
