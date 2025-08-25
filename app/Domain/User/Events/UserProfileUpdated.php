<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserProfileUpdated extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public array $updates,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
