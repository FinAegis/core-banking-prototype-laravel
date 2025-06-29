<?php

declare(strict_types=1);

namespace App\Domain\Banking\Exceptions;

class AccountNotFoundException extends \Exception
{
    public function __construct(string $message = "Bank account not found", int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}