<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use App\Domain\Compliance\Models\ComplianceAlert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AlertEscalated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public ComplianceAlert $alert,
        public Collection $similarAlerts
    ) {
    }
}
