<?php

namespace App\Domain\Stablecoin\Oracles;

use App\Domain\Stablecoin\Contracts\OracleConnector;
use App\Domain\Stablecoin\ValueObjects\PriceData;
use App\Domain\Exchange\Projections\LiquidityPool;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class InternalAMMOracle implements OracleConnector
{
    public function getPrice(string $base, string $quote): PriceData
    {
        try {
            // Find liquidity pool for this pair
            $pool = LiquidityPool::where(function ($query) use ($base, $quote) {
                $query->where('base_currency', $base)->where('quote_currency', $quote);
            })->orWhere(function ($query) use ($base, $quote) {
                $query->where('base_currency', $quote)->where('quote_currency', $base);
            })->first();
            
            if (!$pool) {
                throw new \RuntimeException("No liquidity pool found for {$base}/{$quote}");
            }
            
            // Calculate price from pool reserves
            $price = $this->calculatePrice($pool, $base, $quote);
            
            return new PriceData(
                base: $base,
                quote: $quote,
                price: $price,
                source: 'internal_amm',
                timestamp: $pool->updated_at,
                volume24h: $pool->volume_24h,
                changePercent24h: null,
                metadata: [
                    'pool_id' => $pool->pool_id,
                    'liquidity' => $pool->base_reserve + $pool->quote_reserve,
                    'total_shares' => $pool->total_shares,
                    'k_value' => BigDecimal::of($pool->base_reserve)
                        ->multipliedBy($pool->quote_reserve)
                        ->__toString()
                ]
            );
        } catch (\Exception $e) {
            Log::error("Internal AMM oracle error: {$e->getMessage()}");
            throw $e;
        }
    }
    
    public function getMultiplePrices(array $pairs): array
    {
        $prices = [];
        
        foreach ($pairs as $pair) {
            try {
                [$base, $quote] = explode('/', $pair);
                $prices[$pair] = $this->getPrice($base, $quote);
            } catch (\Exception $e) {
                Log::warning("Failed to get AMM price for {$pair}: {$e->getMessage()}");
            }
        }
        
        return $prices;
    }
    
    public function getHistoricalPrice(string $base, string $quote, Carbon $timestamp): PriceData
    {
        // For historical prices, we'd need to implement pool state snapshots
        throw new \RuntimeException("Historical AMM prices not yet implemented");
    }
    
    public function isHealthy(): bool
    {
        try {
            // Check if we have active pools
            return LiquidityPool::where('is_active', true)->exists();
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function getSourceName(): string
    {
        return 'internal_amm';
    }
    
    public function getPriority(): int
    {
        return 3; // Tertiary priority, used for cross-validation
    }
    
    /**
     * Calculate price from pool reserves using constant product formula
     */
    private function calculatePrice(LiquidityPool $pool, string $base, string $quote): string
    {
        $baseReserve = BigDecimal::of($pool->base_reserve);
        $quoteReserve = BigDecimal::of($pool->quote_reserve);
        
        if ($pool->base_currency === $base) {
            // Price = quote_reserve / base_reserve
            return $quoteReserve->dividedBy($baseReserve, 8, \Brick\Math\RoundingMode::HALF_UP)->__toString();
        } else {
            // Inverted pair, price = base_reserve / quote_reserve
            return $baseReserve->dividedBy($quoteReserve, 8, \Brick\Math\RoundingMode::HALF_UP)->__toString();
        }
    }
}