<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use RuntimeException;

/**
 * Wallet ownership could not be checked against the identity provider
 * (Privy unreachable or not configured) while verification is enforced.
 * Registration fails closed. HTTP 503 ADDRESS_VERIFICATION_UNAVAILABLE.
 */
class AddressOwnershipUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Wallet ownership could not be verified right now. Please try again shortly.', 503);
    }
}
