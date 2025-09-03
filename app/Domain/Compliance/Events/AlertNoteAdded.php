<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AlertNoteAdded extends ShouldBeStored
{
    public function __construct(
        public readonly string $alertId,
        public readonly string $note,
        public readonly string $addedBy,
        public readonly array $attachments,
        public readonly DateTimeImmutable $occurredAt
    ) {
    }
}
