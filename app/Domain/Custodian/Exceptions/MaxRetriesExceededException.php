<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Exceptions;

use Exception;

class MaxRetriesExceededException extends Exception
{
    public function __construct(string $message = 'Maximum retry attempts exceeded', int $code = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}