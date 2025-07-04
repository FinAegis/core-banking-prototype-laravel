<?php

namespace App\Domain\Stablecoin\Services;

use App\Domain\Stablecoin\Contracts\OracleConnector;
use App\Domain\Stablecoin\ValueObjects\AggregatedPrice;
use App\Domain\Stablecoin\ValueObjects\PriceData;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OracleAggregator
{
    private Collection $oracles;
    private int $minOracles = 2;
    private float $maxDeviation = 0.02; // 2% max deviation
    
    public function __construct()
    {
        $this->oracles = collect();
    }
    
    /**
     * Register an oracle connector
     */
    public function registerOracle(OracleConnector $oracle): self
    {
        $this->oracles->push($oracle);
        $this->oracles = $this->oracles->sortBy(fn($o) => $o->getPriority());
        return $this;
    }
    
    /**
     * Get aggregated price from multiple oracles
     */
    public function getAggregatedPrice(string $base, string $quote): AggregatedPrice
    {
        $cacheKey = "oracle_price_{$base}_{$quote}";
        
        return Cache::remember($cacheKey, 60, function () use ($base, $quote) {
            $prices = $this->collectPrices($base, $quote);
            
            if ($prices->count() < $this->minOracles) {
                throw new \RuntimeException("Insufficient oracle responses. Got {$prices->count()}, need {$this->minOracles}");
            }
            
            $this->validatePriceDeviation($prices);
            
            return $this->calculateAggregatedPrice($prices);
        });
    }
    
    /**
     * Collect prices from all healthy oracles
     */
    private function collectPrices(string $base, string $quote): Collection
    {
        $prices = collect();
        
        foreach ($this->oracles as $oracle) {
            try {
                if (!$oracle->isHealthy()) {
                    Log::warning("Oracle {$oracle->getSourceName()} is unhealthy, skipping");
                    continue;
                }
                
                $price = $oracle->getPrice($base, $quote);
                
                if (!$price->isStale()) {
                    $prices->push($price);
                }
            } catch (\Exception $e) {
                Log::error("Oracle {$oracle->getSourceName()} failed: {$e->getMessage()}");
            }
        }
        
        return $prices;
    }
    
    /**
     * Validate that prices don't deviate too much
     */
    private function validatePriceDeviation(Collection $prices): void
    {
        if ($prices->isEmpty()) {
            return;
        }
        
        $base = $prices->first()->base;
        $quote = $prices->first()->quote;
        if ($prices->count() < 2) {
            return;
        }
        
        $values = $prices->map(fn($p) => BigDecimal::of($p->price));
        $min = $values->min();
        $max = $values->max();
        
        // Calculate average manually for BigDecimal
        $sum = $values->reduce(fn($carry, $item) => $carry->plus($item), BigDecimal::of('0'));
        $avg = $sum->dividedBy($values->count(), 18, RoundingMode::HALF_UP);
        
        $deviation = $max->minus($min)->dividedBy($avg, 4, RoundingMode::UP);
        
        if ($deviation->toFloat() > $this->maxDeviation) {
            Log::warning("Price deviation exceeds threshold", [
                'deviation' => $deviation->toFloat(),
                'threshold' => $this->maxDeviation,
                'prices' => $prices->map(fn($p) => [
                    'source' => $p->source,
                    'price' => $p->price
                ])->toArray()
            ]);
            
            // Emit event for monitoring
            event(new \App\Domain\Stablecoin\Events\OracleDeviationDetected(
                base: $base,
                quote: $quote,
                deviation: $deviation->toFloat(),
                prices: $prices->toArray()
            ));
        }
    }
    
    /**
     * Calculate the aggregated price using median
     */
    private function calculateAggregatedPrice(Collection $prices): AggregatedPrice
    {
        $values = $prices->map(fn($p) => BigDecimal::of($p->price))->sort()->values();
        $count = $values->count();
        
        // Calculate median
        if ($count % 2 === 0) {
            $median = $values[$count / 2 - 1]
                ->plus($values[$count / 2])
                ->dividedBy(2, 18, RoundingMode::HALF_UP);
        } else {
            $median = $values[intval($count / 2)];
        }
        
        return new AggregatedPrice(
            base: $prices->first()->base,
            quote: $prices->first()->quote,
            price: $median->toScale(8, RoundingMode::HALF_UP)->__toString(),
            sources: $prices->map(fn($p) => [
                'name' => $p->source,
                'price' => $p->price,
                'timestamp' => $p->timestamp->toIso8601String()
            ])->toArray(),
            aggregationMethod: 'median',
            timestamp: now(),
            confidence: $this->calculateConfidence($prices, $median)
        );
    }
    
    /**
     * Calculate confidence score based on price agreement
     */
    private function calculateConfidence(Collection $prices, BigDecimal $median): float
    {
        if ($prices->count() === 1) {
            return 0.5;
        }
        
        $deviations = $prices->map(function ($price) use ($median) {
            $value = BigDecimal::of($price->price);
            return $value->minus($median)->abs()->dividedBy($median, 4, RoundingMode::UP)->toFloat();
        });
        
        $avgDeviation = $deviations->count() > 0 ? $deviations->sum() / $deviations->count() : 0;
        
        // Convert deviation to confidence (0-1)
        // 0% deviation = 100% confidence
        // 5% deviation = 0% confidence
        return max(0, min(1, 1 - ($avgDeviation * 20)));
    }
    
    /**
     * Get historical aggregated price
     */
    public function getHistoricalAggregatedPrice(string $base, string $quote, Carbon $timestamp): AggregatedPrice
    {
        $prices = collect();
        
        foreach ($this->oracles as $oracle) {
            try {
                $price = $oracle->getHistoricalPrice($base, $quote, $timestamp);
                $prices->push($price);
            } catch (\Exception $e) {
                Log::warning("Oracle {$oracle->getSourceName()} historical price failed: {$e->getMessage()}");
            }
        }
        
        if ($prices->isEmpty()) {
            throw new \RuntimeException("No historical prices available");
        }
        
        return $this->calculateAggregatedPrice($prices);
    }
}