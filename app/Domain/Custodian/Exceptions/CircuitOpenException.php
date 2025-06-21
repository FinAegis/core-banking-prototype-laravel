<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Exceptions;

use Exception;

class CircuitOpenException extends Exception
{
    public function __construct(string $message = 'Circuit breaker is open', int $code = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}