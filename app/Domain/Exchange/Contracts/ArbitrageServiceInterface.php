<?php

namespace App\Domain\Exchange\Contracts;

interface ArbitrageServiceInterface
{
    /**
     * Find arbitrage opportunities
     */
    public function findOpportunities(string $symbol): array;
    
    /**
     * Execute arbitrage opportunity
     */
    public function executeArbitrage(array $opportunity): array;
    
    /**
     * Calculate profitability of opportunity
     */
    public function calculateProfitability(array $opportunity): float;
}