<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PaymentNotificationSent extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentDid,
        public readonly string $notificationType,
        public readonly string $paymentId,
        public readonly Carbon $timestamp
    ) {
    }
}
