<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AlertTriggeredEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $alertName,
        public readonly string $severity,
        public readonly string $message,
        public readonly array $context,
        public readonly Carbon $triggeredAt
    ) {
    }
}
