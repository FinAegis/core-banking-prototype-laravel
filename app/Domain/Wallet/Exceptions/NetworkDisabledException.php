<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use RuntimeException;

/**
 * Thrown when an EVM send is requested for a network that is not in
 * `wallet.evm.enabled_networks`. Used as a feature-flag style guard so we
 * can disable a chain in production without yanking code. HTTP 422
 * Unprocessable Entity is the canonical mapping.
 */
class NetworkDisabledException extends RuntimeException
{
    protected $code = 422;

    public function errorCode(): string
    {
        return 'NETWORK_DISABLED';
    }
}
