<?php

namespace App\Domain\Compliance\Events;

use App\Models\SuspiciousActivityReport;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SARSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public function __construct(
        public readonly SuspiciousActivityReport $sar
    ) {}
}