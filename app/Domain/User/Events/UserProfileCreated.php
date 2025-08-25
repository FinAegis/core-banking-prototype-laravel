<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserProfileCreated extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public string $email,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $phoneNumber,
        public array $metadata,
        public DateTimeImmutable $createdAt
    ) {
    }
}
