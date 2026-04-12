<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Exceptions;

use RuntimeException;

final class InvalidWebhookSignatureException extends RuntimeException
{
    public function __construct(string $message = 'Invalid webhook signature')
    {
        parent::__construct($message);
    }
}
