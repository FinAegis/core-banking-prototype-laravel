<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserProfileVerified extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public string $verificationType,
        public string $verifiedBy,
        public DateTimeImmutable $verifiedAt
    ) {
    }
}
