<?php

namespace App\Domain\Compliance\Events;

use App\Models\CustomerRiskProfile;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiskLevelChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public function __construct(
        public readonly CustomerRiskProfile $profile,
        public readonly string $oldLevel,
        public readonly string $newLevel
    ) {}
}