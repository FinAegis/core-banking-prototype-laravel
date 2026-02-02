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

    protected string $errorCode;

    public function __construct(string $message, string $errorCode, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
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

        return new self($message, self::CODE_INVALID_USER_OP_HASH);
    }

    public static function invalidDeviceShard(string $details = ''): self
    {
        $message = 'Invalid device shard proof';
        if ($details !== '') {
            $message .= ": {$details}";
        }

        return new self($message, self::CODE_INVALID_DEVICE_SHARD);
    }

    public static function biometricVerificationFailed(string $details = ''): self
    {
        $message = 'Biometric verification failed';
        if ($details !== '') {
            $message .= ": {$details}";
        }

        return new self($message, self::CODE_BIOMETRIC_FAILED);
    }

    public static function shardUnavailable(string $details = ''): self
    {
        $message = 'Authentication shard not available';
        if ($details !== '') {
            $message .= ": {$details}";
        }

        return new self($message, self::CODE_SHARD_UNAVAILABLE);
    }

    public static function signingFailed(string $details = ''): self
    {
        $message = 'Failed to sign UserOperation';
        if ($details !== '') {
            $message .= ": {$details}";
        }

        return new self($message, self::CODE_SIGNING_FAILED);
    }
}
