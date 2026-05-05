<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when an incoming signature payload cannot be decoded or has the
 * wrong byte-length for the target curve (ed25519 signatures are exactly
 * 64 bytes). HTTP 422 Unprocessable Entity.
 */
class InvalidSignatureException extends InvalidArgumentException
{
    protected $code = 422;

    public function errorCode(): string
    {
        return 'INVALID_SIGNATURE';
    }
}
