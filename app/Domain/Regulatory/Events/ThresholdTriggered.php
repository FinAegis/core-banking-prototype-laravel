<?php

namespace App\Domain\Regulatory\Events;

use App\Domain\Regulatory\Models\RegulatoryThreshold;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThresholdTriggered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public RegulatoryThreshold $threshold;
    public array $context;
    public ?string $entityType;
    public ?string $entityId;

    public function __construct(
        RegulatoryThreshold $threshold, 
        array $context, 
        ?string $entityType = null,
        ?string $entityId = null
    ) {
        $this->threshold = $threshold;
        $this->context = $context;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
    }

    /**
     * Get the tags that should be assigned to the event
     */
    public function tags(): array
    {
        $tags = [
            'regulatory',
            'threshold_triggered',
            'threshold:' . $this->threshold->threshold_code,
            'category:' . $this->threshold->category,
            'report_type:' . $this->threshold->report_type,
            'jurisdiction:' . $this->threshold->jurisdiction,
        ];

        if ($this->entityType) {
            $tags[] = 'entity_type:' . $this->entityType;
        }

        if ($this->entityId) {
            $tags[] = 'entity_id:' . $this->entityId;
        }

        return $tags;
    }
}