<?php

declare(strict_types=1);

namespace App\Domain\Banking\Exceptions;

class TransferException extends \Exception
{
    public function __construct(string $message = "Transfer failed", int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}