<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a sender or recipient address is not a valid Base58-encoded
 * 32-byte ed25519 public key (Solana). HTTP 422 Unprocessable Entity.
 */
class InvalidAddressException extends InvalidArgumentException
{
    protected $code = 422;

    public function errorCode(): string
    {
        return 'INVALID_ADDRESS';
    }
}
