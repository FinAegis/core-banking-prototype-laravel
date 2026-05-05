<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when the caller supplies a malformed amount string (non-numeric,
 * negative, zero, or with more decimal precision than the target asset
 * supports — e.g. '1.5000001' against a 6-decimal stablecoin). HTTP 422
 * Unprocessable Entity.
 */
class InvalidAmountException extends InvalidArgumentException
{
    protected $code = 422;

    public function errorCode(): string
    {
        return 'INVALID_AMOUNT';
    }
}
