<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\ValueObjects;

enum SpanStatus: string
{
    case OK = 'ok';
    case ERROR = 'error';
    case CANCELLED = 'cancelled';
    case UNKNOWN = 'unknown';

    public function isError(): bool
    {
        return $this === self::ERROR;
    }

    public function isSuccess(): bool
    {
        return $this === self::OK;
    }
}
