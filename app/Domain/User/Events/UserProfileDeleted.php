<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserProfileDeleted extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public string $reason,
        public string $deletedBy,
        public DateTimeImmutable $deletedAt
    ) {
    }
}
