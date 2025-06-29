<?php

namespace App\Domain\Fraud\Events;

use App\Models\FraudCase;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FraudCaseResolved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public FraudCase $fraudCase;

    public function __construct(FraudCase $fraudCase)
    {
        $this->fraudCase = $fraudCase;
    }

    /**
     * Get the tags that should be assigned to the event
     */
    public function tags(): array
    {
        return [
            'fraud',
            'fraud_case',
            'case_resolved',
            'case:' . $this->fraudCase->case_number,
            'outcome:' . $this->fraudCase->outcome,
            'resolution:' . $this->fraudCase->resolution,
        ];
    }
}