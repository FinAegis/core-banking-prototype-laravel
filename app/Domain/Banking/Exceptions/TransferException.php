<?php

declare(strict_types=1);

namespace App\Domain\Banking\Exceptions;

use Exception;
use Throwable;

class TransferException extends Exception
{
    public function __construct(string $message = 'Transfer failed', int $code = 500, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
