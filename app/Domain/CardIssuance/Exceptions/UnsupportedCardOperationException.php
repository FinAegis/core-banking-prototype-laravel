<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Exceptions;

use GraphQL\Error\ClientAware;
use RuntimeException;

/**
 * Thrown when a CardIssuerInterface operation isn't supported by the active
 * issuer. FinCard's prefunded model can't express createCard through the generic
 * interface (it needs a card-type + amount + funded account) — use the
 * FinCard-specific POST /v1/cards/fincard/open — and it has no Apple/Google Pay
 * push provisioning in v1. The demo issuer throws it for createCard and
 * getProvisioningData under APP_ENV=production.
 *
 * Client-safe: the message names no provider data, so GraphQL returns it
 * as-is instead of "Internal server error".
 */
final class UnsupportedCardOperationException extends RuntimeException implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
