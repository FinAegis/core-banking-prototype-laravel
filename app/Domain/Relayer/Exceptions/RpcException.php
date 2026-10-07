<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Exceptions;

use App\Support\SecretRedactor;
use RuntimeException;

/**
 * Structured exception for JSON-RPC errors from Ethereum nodes and bundlers.
 *
 * The message and error data are passed through {@see SecretRedactor}: they
 * end up in logs, persisted send records and API responses, and transport
 * errors embed the provider URL (which carries the API key).
 */
class RpcException extends RuntimeException
{
    public readonly ?string $rpcErrorData;

    public function __construct(
        string $message,
        public readonly string $rpcMethod,
        public readonly int $rpcErrorCode = 0,
        ?string $rpcErrorData = null,
    ) {
        $this->rpcErrorData = $rpcErrorData === null ? null : SecretRedactor::redact($rpcErrorData);

        parent::__construct(SecretRedactor::redact($message), $rpcErrorCode);
    }

    /**
     * @param  array<string, mixed>  $error
     */
    public static function fromRpcError(string $method, array $error): self
    {
        $errorData = null;
        if (isset($error['data'])) {
            $errorData = is_string($error['data'])
                ? $error['data']
                : (string) json_encode($error['data']);
        }

        return new self(
            message: (string) ($error['message'] ?? 'Unknown RPC error'),
            rpcMethod: $method,
            rpcErrorCode: (int) ($error['code'] ?? 0),
            rpcErrorData: $errorData,
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
