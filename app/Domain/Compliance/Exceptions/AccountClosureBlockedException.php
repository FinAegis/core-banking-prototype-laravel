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
     * @param array<int, string> $reasons     sentences for people
     * @param array<int, string> $reasonCodes stable codes for clients, at the same positions
     */
    public function __construct(
        public readonly array $reasons,
        public readonly array $reasonCodes = [],
    ) {
        parent::__construct('The account cannot be deleted at this time: ' . implode('; ', $reasons));
    }
}
