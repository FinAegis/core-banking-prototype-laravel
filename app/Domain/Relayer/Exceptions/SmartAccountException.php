<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Exceptions;

use Exception;

/**
 * Exception for smart account related errors.
 */
class SmartAccountException extends Exception
{
    public const CODE_ACCOUNT_EXISTS = 'ERR_RELAYER_101';

    public const CODE_ACCOUNT_NOT_FOUND = 'ERR_RELAYER_102';

    public const CODE_DEPLOYMENT_FAILED = 'ERR_RELAYER_103';

    public const CODE_NONCE_MISMATCH = 'ERR_RELAYER_104';

    public const CODE_INVALID_NETWORK = 'ERR_RELAYER_105';

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatusCode = 400,
    ) {
        parent::__construct($message);
    }

    public static function accountExists(string $ownerAddress, string $network): self
    {
        return new self(
            "Smart account already exists for owner {$ownerAddress} on {$network}",
            self::CODE_ACCOUNT_EXISTS,
            409
        );
    }

    public static function accountNotFound(string $ownerAddress, string $network): self
    {
        return new self(
            "Smart account not found for owner {$ownerAddress} on {$network}",
            self::CODE_ACCOUNT_NOT_FOUND,
            404
        );
    }

    public static function deploymentFailed(string $reason): self
    {
        return new self(
            "Smart account deployment failed: {$reason}",
            self::CODE_DEPLOYMENT_FAILED,
            500
        );
    }

    public static function nonceMismatch(int $expected, int $provided): self
    {
        return new self(
            "Nonce mismatch: expected {$expected}, got {$provided}",
            self::CODE_NONCE_MISMATCH,
            400
        );
    }

    public static function invalidNetwork(string $network): self
    {
        return new self(
            "Invalid or unsupported network: {$network}",
            self::CODE_INVALID_NETWORK,
            400
        );
    }

    /**
     * Get additional context for logging.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'error_code'  => $this->errorCode,
            'http_status' => $this->httpStatusCode,
        ];
    }
}
