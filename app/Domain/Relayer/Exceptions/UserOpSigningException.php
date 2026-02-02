<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when UserOperation signing fails.
 */
class UserOpSigningException extends RuntimeException
{
    public const CODE_INVALID_USER_OP_HASH = 'ERR_RELAYER_201';

    public const CODE_INVALID_DEVICE_SHARD = 'ERR_RELAYER_202';

    public const CODE_BIOMETRIC_FAILED = 'ERR_RELAYER_203';

    public const CODE_SHARD_UNAVAILABLE = 'ERR_RELAYER_204';

    public const CODE_SIGNING_FAILED = 'ERR_RELAYER_205';

    public const CODE_RATE_LIMITED = 'ERR_RELAYER_206';

    public readonly string $errorCode;

    public readonly int $httpStatusCode;

    public function __construct(string $message, string $errorCode, int $httpStatusCode = 400, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function invalidUserOpHash(string $details = ''): self
    {
        $message = 'Invalid UserOperation hash format';
        if ($details !== '') {
            $message .= ": {$details}";
        }

        return new self($message, self::CODE_INVALID_USER_OP_HASH, 400);
    }

    public static function invalidDeviceShard(string $details = ''): self
    {
        $message = 'Invalid device shard proof';
        if ($details !== '') {
            $message .= ": {$details}";
        }

        return new self($message, self::CODE_INVALID_DEVICE_SHARD, 400);
    }

    public static function biometricVerificationFailed(string $details = ''): self
    {
        $message = 'Biometric verification failed';
        if ($details !== '') {
            $message .= ": {$details}";
        }

        return new self($message, self::CODE_BIOMETRIC_FAILED, 401);
    }

    public static function shardUnavailable(string $details = ''): self
    {
        $message = 'Authentication shard not available';
        if ($details !== '') {
            $message .= ": {$details}";
        }

        return new self($message, self::CODE_SHARD_UNAVAILABLE, 503);
    }

    public static function signingFailed(string $details = ''): self
    {
        $message = 'Failed to sign UserOperation';
        if ($details !== '') {
            $message .= ": {$details}";
        }

        return new self($message, self::CODE_SIGNING_FAILED, 500);
    }

    public static function rateLimited(string $details = ''): self
    {
        $message = 'Rate limit exceeded for UserOperation signing';
        if ($details !== '') {
            $message .= ": {$details}";
        }

        return new self($message, self::CODE_RATE_LIMITED, 429);
    }
}
