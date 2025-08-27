<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Exceptions;

use Exception;

class RateLimitException extends RateProviderException
{
    public function __construct(string $message = 'Rate limit exceeded', int $code = 429, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
