<?php

declare(strict_types=1);

namespace App\Domain\AI\Exceptions;

use Exception;

class MCPException extends Exception
{
    public function __construct(string $message, int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
