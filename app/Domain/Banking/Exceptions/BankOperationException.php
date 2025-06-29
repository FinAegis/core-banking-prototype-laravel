<?php

declare(strict_types=1);

namespace App\Domain\Banking\Exceptions;

class BankOperationException extends \Exception
{
    public function __construct(string $message = "Bank operation failed", int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}