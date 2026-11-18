<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Exceptions;

use RuntimeException;

/**
 * Thrown when a CardIssuerInterface operation isn't supported by the active
 * issuer. FinCard's prefunded model can't express createCard through the generic
 * interface (it needs a card-type + amount + funded account) — use the
 * FinCard-specific POST /v1/cards/fincard/open — and it has no Apple/Google Pay
 * push provisioning in v1.
 */
final class UnsupportedCardOperationException extends RuntimeException
{
}
