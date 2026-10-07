<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Exceptions;

use RuntimeException;

/**
 * Too many subscription requests from one IP address or for one email
 * address within the throttle window.
 */
class SubscriptionThrottledException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many subscription requests. Please try again later.');
    }
}
