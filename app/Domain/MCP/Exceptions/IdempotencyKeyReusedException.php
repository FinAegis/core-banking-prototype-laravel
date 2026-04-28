<?php

declare(strict_types=1);

namespace App\Domain\MCP\Exceptions;

use RuntimeException;

class IdempotencyKeyReusedException extends RuntimeException
{
}
