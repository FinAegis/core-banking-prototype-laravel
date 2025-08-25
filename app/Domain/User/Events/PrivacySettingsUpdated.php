<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PrivacySettingsUpdated extends ShouldBeStored
{
    public function __construct(
        public string $userId,
        public array $settings,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
