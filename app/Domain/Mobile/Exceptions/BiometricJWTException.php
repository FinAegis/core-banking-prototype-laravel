<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown for Biometric JWT verification failures.
 */
class BiometricJWTException extends RuntimeException
{
    public const CODE_INVALID_TOKEN = 'ERR_BIOMETRIC_JWT_001';

    public const CODE_EXPIRED_TOKEN = 'ERR_BIOMETRIC_JWT_002';

    public const CODE_INVALID_SIGNATURE = 'ERR_BIOMETRIC_JWT_003';

    public const CODE_INVALID_CLAIMS = 'ERR_BIOMETRIC_JWT_004';

    public const CODE_DEVICE_MISMATCH = 'ERR_BIOMETRIC_JWT_005';

    public const CODE_USER_MISMATCH = 'ERR_BIOMETRIC_JWT_006';

    public const CODE_SESSION_INVALID = 'ERR_BIOMETRIC_JWT_007';

    public const CODE_ATTESTATION_FAILED = 'ERR_BIOMETRIC_JWT_008';

    public readonly string $errorCode;

    public function __construct(string $message, string $errorCode, ?Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, 0, $previous);
    }

    public static function invalidToken(string $details = ''): self
    {
        $message = 'Invalid biometric JWT token';
        if ($details !== '') {
            $message .= ': ' . $details;
        }

        return new self($message, self::CODE_INVALID_TOKEN);
    }

    public static function expiredToken(): self
    {
        return new self('Biometric JWT token has expired', self::CODE_EXPIRED_TOKEN);
    }

    public static function invalidSignature(): self
    {
        return new self('Invalid JWT signature', self::CODE_INVALID_SIGNATURE);
    }

    public static function invalidClaims(string $details = ''): self
    {
        $message = 'Invalid JWT claims';
        if ($details !== '') {
            $message .= ': ' . $details;
        }

        return new self($message, self::CODE_INVALID_CLAIMS);
    }

    public static function deviceMismatch(): self
    {
        return new self('Device does not match token claims', self::CODE_DEVICE_MISMATCH);
    }

    public static function userMismatch(): self
    {
        return new self('User does not match token claims', self::CODE_USER_MISMATCH);
    }

    public static function sessionInvalid(): self
    {
        return new self('Biometric session is invalid or expired', self::CODE_SESSION_INVALID);
    }

    public static function attestationFailed(string $details = ''): self
    {
        $message = 'Device attestation verification failed';
        if ($details !== '') {
            $message .= ': ' . $details;
        }

        return new self($message, self::CODE_ATTESTATION_FAILED);
    }
}
