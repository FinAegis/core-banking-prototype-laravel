<?php

namespace App\Domain\Stablecoin\Oracles;

use App\Domain\Stablecoin\Contracts\OracleConnector;
use App\Domain\Stablecoin\ValueObjects\PriceData;
use App\Models\LiquidityPool;
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
                $query->where('base_asset', $base)->where('quote_asset', $quote);
            })->orWhere(function ($query) use ($base, $quote) {
                $query->where('base_asset', $quote)->where('quote_asset', $base);
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
                    'liquidity' => $pool->total_liquidity,
                    'total_shares' => $pool->total_shares,
                    'k_value' => BigDecimal::of($pool->base_balance)
                        ->multipliedBy($pool->quote_balance)
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
            return LiquidityPool::where('status', 'active')->exists();
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
        $baseBalance = BigDecimal::of($pool->base_balance);
        $quoteBalance = BigDecimal::of($pool->quote_balance);
        
        if ($pool->base_asset === $base) {
            // Price = quote_balance / base_balance
            return $quoteBalance->dividedBy($baseBalance, 8, \Brick\Math\RoundingMode::HALF_UP)->__toString();
        } else {
            // Inverted pair, price = base_balance / quote_balance
            return $baseBalance->dividedBy($quoteBalance, 8, \Brick\Math\RoundingMode::HALF_UP)->__toString();
        }
    }
}