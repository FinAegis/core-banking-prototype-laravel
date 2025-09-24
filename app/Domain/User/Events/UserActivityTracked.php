<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserActivityTracked extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public string $activity,
        public array $context,
        public DateTimeImmutable $trackedAt,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $sessionId = null
    ) {
    }
}
