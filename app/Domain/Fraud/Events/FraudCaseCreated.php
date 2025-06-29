<?php

namespace App\Domain\Fraud\Events;

use App\Models\FraudCase;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FraudCaseCreated
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
            'case_created',
            'case:' . $this->fraudCase->case_number,
            'priority:' . $this->fraudCase->priority,
        ];
    }
}