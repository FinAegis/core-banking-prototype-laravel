<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Events;

use App\Domain\Fraud\Models\AnomalyDetection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnomalyDetected
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly AnomalyDetection $anomalyDetection,
    ) {
    }

    /**
     * Get the tags that should be assigned to the event.
     */
    public function tags(): array
    {
        return [
            'fraud',
            'anomaly',
            'anomaly_type:' . $this->anomalyDetection->anomaly_type->value,
            'severity:' . $this->anomalyDetection->severity,
            'entity_type:' . class_basename($this->anomalyDetection->entity_type),
        ];
    }
}
