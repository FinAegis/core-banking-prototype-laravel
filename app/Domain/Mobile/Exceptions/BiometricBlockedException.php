<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Exceptions;

use Carbon\Carbon;
use Exception;

/**
 * Exception thrown when a device's biometric authentication is temporarily blocked.
 *
 * This occurs when the device exceeds the maximum number of failed biometric
 * authentication attempts within a time window.
 */
class BiometricBlockedException extends Exception
{
    public function __construct(
        public readonly Carbon $blockedUntil,
        string $message = 'Biometric authentication is temporarily blocked due to too many failed attempts.',
    ) {
        parent::__construct($message);
    }

    /**
     * Get the HTTP status code for this exception.
     */
    public function getHttpStatusCode(): int
    {
        return 429; // Too Many Requests
    }

    /**
     * Get the number of seconds until the block expires.
     */
    public function getRetryAfterSeconds(): int
    {
        // Calculate explicitly to avoid Carbon diff behavior variations
        $seconds = $this->blockedUntil->getTimestamp() - now()->getTimestamp();

        return max(0, $seconds);
    }

    /**
     * Get additional context for logging.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'blocked_until'       => $this->blockedUntil->toIso8601String(),
            'retry_after_seconds' => $this->getRetryAfterSeconds(),
        ];
    }
}
