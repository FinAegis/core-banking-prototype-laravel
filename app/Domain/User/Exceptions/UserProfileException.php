<?php

declare(strict_types=1);

namespace App\Domain\User\Exceptions;

use DomainException;

class UserProfileException extends DomainException
{
    public static function noValidFieldsToUpdate(): self
    {
        return new self('No valid fields provided for update.');
    }

    public static function alreadyVerified(string $userId): self
    {
        return new self("User profile {$userId} is already verified.");
    }

    public static function alreadySuspended(string $userId): self
    {
        return new self("User profile {$userId} is already suspended.");
    }

    public static function alreadyDeleted(string $userId): self
    {
        return new self("User profile {$userId} is already deleted.");
    }

    public static function notFound(string $userId): self
    {
        return new self("User profile {$userId} not found.");
    }

    public static function invalidStatus(string $status): self
    {
        return new self("Invalid user profile status: {$status}");
    }

    public static function cannotPerformAction(string $userId, string $action, string $reason): self
    {
        return new self("Cannot perform {$action} on user {$userId}: {$reason}");
    }
}
