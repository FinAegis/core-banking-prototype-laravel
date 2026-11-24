<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Exceptions;

use RuntimeException;

/**
 * A rule does not allow the account to be deleted yet, for example a
 * positive balance or an identity check in progress. Nothing was erased.
 */
final class AccountClosureBlockedException extends RuntimeException
{
    /**
     * @param array<int, string> $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct('The account cannot be deleted at this time: ' . implode('; ', $reasons));
    }
}
