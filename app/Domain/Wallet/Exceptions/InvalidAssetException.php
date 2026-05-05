<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when the requested asset symbol is not a supported token for the
 * target network (e.g. requesting MATIC on Solana, or a non-stablecoin in
 * the v1 send pipeline). HTTP 422 Unprocessable Entity.
 */
class InvalidAssetException extends InvalidArgumentException
{
    protected $code = 422;

    public function errorCode(): string
    {
        return 'INVALID_ASSET';
    }
}
