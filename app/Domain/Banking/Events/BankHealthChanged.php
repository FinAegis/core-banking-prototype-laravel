<?php

declare(strict_types=1);

namespace App\Domain\Banking\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BankHealthChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public function __construct(
        public readonly string $bankCode,
        public readonly ?string $previousStatus,
        public readonly string $currentStatus,
        public readonly array $healthData
    ) {}
    
    /**
     * Check if bank became unhealthy
     */
    public function becameUnhealthy(): bool
    {
        return $this->previousStatus === 'healthy' && $this->currentStatus !== 'healthy';
    }
    
    /**
     * Check if bank recovered
     */
    public function recovered(): bool
    {
        return $this->previousStatus !== 'healthy' && $this->currentStatus === 'healthy';
    }
}