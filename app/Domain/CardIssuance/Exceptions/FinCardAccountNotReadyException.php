<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Exceptions;

use RuntimeException;

/**
 * Thrown before any FinCard call that moves money onto a card (open, top-up)
 * when the user has no active funding account with a FinCard account id, or
 * when the card is not bound to the user's current funding account.
 *
 * FinCard is called with the platform's tenant-wide credentials, so it cannot
 * tell which customer a request is for. The per-user funding account id is
 * the only thing that ties a card load to the user's own funds; without it the
 * request is refused (HTTP 409 ERR_CARDS_020) instead of being sent with a
 * blank account id.
 */
final class FinCardAccountNotReadyException extends RuntimeException
{
}
