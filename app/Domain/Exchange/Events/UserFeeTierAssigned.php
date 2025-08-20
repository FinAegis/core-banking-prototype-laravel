<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class UserFeeTierAssigned extends ShouldBeStored
{
    public function __construct(
        public readonly string $userId,
        public readonly string $tier,
        public readonly ?string $reason,
        public readonly \DateTimeImmutable|\Illuminate\Support\Carbon $timestamp,
    ) {
    }
}
