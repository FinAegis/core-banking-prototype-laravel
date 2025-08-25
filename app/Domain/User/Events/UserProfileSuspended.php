<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserProfileSuspended extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public string $reason,
        public string $suspendedBy,
        public DateTimeImmutable $suspendedAt
    ) {
    }
}
