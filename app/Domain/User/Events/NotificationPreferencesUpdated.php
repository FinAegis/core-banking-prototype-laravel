<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class NotificationPreferencesUpdated extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public array $preferences,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
