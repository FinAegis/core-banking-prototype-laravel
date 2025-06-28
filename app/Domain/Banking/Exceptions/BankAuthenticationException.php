<?php

declare(strict_types=1);

namespace App\Domain\Banking\Exceptions;

class BankAuthenticationException extends \Exception
{
    public function __construct(string $message = "Bank authentication failed", int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}