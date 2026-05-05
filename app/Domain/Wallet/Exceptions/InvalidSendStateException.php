<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use RuntimeException;

/**
 * Thrown when an operation is attempted on a {@see \App\Domain\Wallet\Models\WalletSendRecord}
 * that is not in a valid state for the transition (e.g. submitting a record
 * that is in an unknown status). HTTP 409 Conflict.
 */
class InvalidSendStateException extends RuntimeException
{
    protected $code = 409;

    public function errorCode(): string
    {
        return 'INVALID_SEND_STATE';
    }
}
