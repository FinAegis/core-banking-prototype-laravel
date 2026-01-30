<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Exceptions;

use Exception;

/**
 * Exception thrown when an attempt is made to register a device that belongs to another user.
 *
 * This is a security-critical exception that indicates a potential account takeover attempt.
 * The device registration flow should not allow transferring devices between users.
 */
class DeviceTakeoverAttemptException extends Exception
{
    public function __construct(
        public readonly string $deviceId,
        public readonly int $existingUserId,
        public readonly int $attemptedUserId,
        string $message = 'Device already registered to another user. Contact support if you believe this is an error.',
    ) {
        parent::__construct($message);
    }

    /**
     * Get the HTTP status code for this exception.
     */
    public function getHttpStatusCode(): int
    {
        return 409; // Conflict
    }

    /**
     * Get additional context for logging.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'device_id'         => $this->deviceId,
            'existing_user_id'  => $this->existingUserId,
            'attempted_user_id' => $this->attemptedUserId,
        ];
    }
}
