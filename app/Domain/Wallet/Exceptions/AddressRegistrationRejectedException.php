<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use InvalidArgumentException;

/**
 * An address could not be registered to the caller: it is reserved, not a
 * wallet of the caller's Privy account, over the per-user cap, or already
 * claimed by someone else.
 *
 * Every one of those reasons surfaces with the same generic message (HTTP
 * 422 INVALID_ADDRESS) so the endpoint cannot be used as an "is this address
 * a customer" oracle. The concrete reason is logged for operators only.
 */
class AddressRegistrationRejectedException extends InvalidArgumentException
{
    public const MESSAGE = 'This address cannot be registered to your account.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE, 422);
    }
}
