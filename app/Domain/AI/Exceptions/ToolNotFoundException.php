<?php

declare(strict_types=1);

namespace App\Domain\AI\Exceptions;

class ToolNotFoundException extends MCPException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 404);
    }
}
