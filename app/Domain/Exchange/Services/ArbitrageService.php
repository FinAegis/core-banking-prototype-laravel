<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Contracts\ArbitrageServiceInterface;

class ArbitrageService implements ArbitrageServiceInterface
{
    public function findOpportunities(string $symbol): array
    {
        return [];
    }
    
    public function executeArbitrage(array $opportunity): array
    {
        return [
            'success' => false,
            'message' => 'Arbitrage execution not implemented',
        ];
    }
    
    public function calculateProfitability(array $opportunity): float
    {
        return 0.0;
    }
}