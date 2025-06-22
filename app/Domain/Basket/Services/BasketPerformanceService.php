<?php

declare(strict_types=1);

namespace App\Domain\Basket\Services;

use App\Models\BasketAsset;
use App\Models\BasketPerformance;
use App\Models\BasketValue;
use App\Models\ComponentPerformance;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BasketPerformanceService
{
    /**
     * Calculate performance for a basket over a specific period.
     */
    public function calculatePerformance(
        BasketAsset $basket,
        string $periodType,
        Carbon $periodStart,
        Carbon $periodEnd
    ): ?BasketPerformance {
        // Get basket values for the period
        $values = $basket->values()
            ->whereBetween('calculated_at', [$periodStart, $periodEnd])
            ->orderBy('calculated_at')
            ->get();
            
        if ($values->count() < 2) {
            Log::warning("Insufficient data to calculate performance for basket {$basket->code}", [
                'period_type' => $periodType,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'value_count' => $values->count(),
            ]);
            return null;
        }
        
        $startValue = $values->first();
        $endValue = $values->last();
        
        // Calculate basic metrics
        $returnValue = $endValue->value - $startValue->value;
        $returnPercentage = $startValue->value > 0 
            ? ($returnValue / $startValue->value) * 100 
            : 0;
            
        // Calculate high, low, and average
        $highValue = $values->max('value');
        $lowValue = $values->min('value');
        $averageValue = $values->avg('value');
        
        // Calculate volatility (standard deviation of daily returns)
        $volatility = $this->calculateVolatility($values);
        
        // Calculate Sharpe ratio (assuming risk-free rate of 2% annually)
        $sharpeRatio = $this->calculateSharpeRatio($returnPercentage, $volatility, $periodStart, $periodEnd);
        
        // Calculate maximum drawdown
        $maxDrawdown = $this->calculateMaxDrawdown($values);
        
        // Create or update performance record
        $performance = BasketPerformance::updateOrCreate(
            [
                'basket_asset_code' => $basket->code,
                'period_type' => $periodType,
                'period_start' => $periodStart,
            ],
            [
                'period_end' => $periodEnd,
                'start_value' => $startValue->value,
                'end_value' => $endValue->value,
                'high_value' => $highValue,
                'low_value' => $lowValue,
                'average_value' => $averageValue,
                'return_value' => $returnValue,
                'return_percentage' => $returnPercentage,
                'volatility' => $volatility,
                'sharpe_ratio' => $sharpeRatio,
                'max_drawdown' => $maxDrawdown,
                'value_count' => $values->count(),
                'metadata' => [
                    'calculation_date' => now()->toIso8601String(),
                    'data_points' => $values->count(),
                ],
            ]
        );
        
        // Calculate component performances
        $this->calculateComponentPerformances($performance, $startValue, $endValue);
        
        return $performance;
    }
    
    /**
     * Calculate performance for all standard periods.
     */
    public function calculateAllPeriods(BasketAsset $basket): Collection
    {
        $performances = collect();
        $now = now();
        
        $periods = [
            'hour' => [$now->copy()->subHour(), $now],
            'day' => [$now->copy()->subDay(), $now],
            'week' => [$now->copy()->subWeek(), $now],
            'month' => [$now->copy()->subMonth(), $now],
            'quarter' => [$now->copy()->subQuarter(), $now],
            'year' => [$now->copy()->subYear(), $now],
        ];
        
        foreach ($periods as $periodType => [$start, $end]) {
            $performance = $this->calculatePerformance($basket, $periodType, $start, $end);
            if ($performance) {
                $performances->push($performance);
            }
        }
        
        // Calculate all-time performance
        $firstValue = $basket->values()->orderBy('calculated_at')->first();
        if ($firstValue && $firstValue->calculated_at->lt($now->copy()->subDay())) {
            $allTimePerformance = $this->calculatePerformance(
                $basket,
                'all_time',
                $firstValue->calculated_at,
                $now
            );
            if ($allTimePerformance) {
                $performances->push($allTimePerformance);
            }
        }
        
        return $performances;
    }
    
    /**
     * Calculate volatility as standard deviation of returns.
     */
    protected function calculateVolatility(Collection $values): float
    {
        if ($values->count() < 2) {
            return 0;
        }
        
        $returns = [];
        $previousValue = null;
        
        foreach ($values as $value) {
            if ($previousValue && $previousValue->value > 0) {
                $returns[] = (($value->value - $previousValue->value) / $previousValue->value) * 100;
            }
            $previousValue = $value;
        }
        
        if (count($returns) < 2) {
            return 0;
        }
        
        $mean = array_sum($returns) / count($returns);
        $variance = 0;
        
        foreach ($returns as $return) {
            $variance += pow($return - $mean, 2);
        }
        
        $variance /= count($returns) - 1;
        
        return sqrt($variance);
    }
    
    /**
     * Calculate Sharpe ratio.
     */
    protected function calculateSharpeRatio(
        float $returnPercentage,
        float $volatility,
        Carbon $periodStart,
        Carbon $periodEnd
    ): ?float {
        if ($volatility == 0) {
            return null;
        }
        
        // Annualize the return and volatility
        $daysInPeriod = $periodStart->diffInDays($periodEnd) ?: 1;
        $periodsPerYear = 365.25 / $daysInPeriod;
        
        // Annualized return
        $annualizedReturn = $returnPercentage * $periodsPerYear;
        
        // Annualized volatility
        $annualizedVolatility = $volatility * sqrt($periodsPerYear);
        
        // Risk-free rate (2% annually)
        $riskFreeRate = 2.0;
        
        // Sharpe ratio
        return ($annualizedReturn - $riskFreeRate) / $annualizedVolatility;
    }
    
    /**
     * Calculate maximum drawdown.
     */
    protected function calculateMaxDrawdown(Collection $values): float
    {
        $maxDrawdown = 0;
        $peak = 0;
        
        foreach ($values as $value) {
            if ($value->value > $peak) {
                $peak = $value->value;
            }
            
            if ($peak > 0) {
                $drawdown = (($peak - $value->value) / $peak) * 100;
                if ($drawdown > $maxDrawdown) {
                    $maxDrawdown = $drawdown;
                }
            }
        }
        
        return $maxDrawdown;
    }
    
    /**
     * Calculate component performances.
     */
    protected function calculateComponentPerformances(
        BasketPerformance $performance,
        BasketValue $startValue,
        BasketValue $endValue
    ): void {
        // Delete existing component performances
        $performance->componentPerformances()->delete();
        
        $startComponents = $startValue->component_values ?? [];
        $endComponents = $endValue->component_values ?? [];
        
        // Get all unique asset codes
        $assetCodes = collect($startComponents)
            ->keys()
            ->merge(collect($endComponents)->keys())
            ->unique();
            
        foreach ($assetCodes as $assetCode) {
            $startData = $startComponents[$assetCode] ?? null;
            $endData = $endComponents[$assetCode] ?? null;
            
            if (!$startData || !$endData) {
                continue;
            }
            
            $startWeight = $startData['weight'] ?? 0;
            $endWeight = $endData['weight'] ?? 0;
            $averageWeight = ($startWeight + $endWeight) / 2;
            
            $startComponentValue = $startData['weighted_value'] ?? 0;
            $endComponentValue = $endData['weighted_value'] ?? 0;
            
            $returnValue = $endComponentValue - $startComponentValue;
            $returnPercentage = $startComponentValue > 0 
                ? ($returnValue / $startComponentValue) * 100 
                : 0;
                
            // Calculate contribution to overall return
            $contributionValue = $returnValue;
            $contributionPercentage = $startValue->value > 0 
                ? ($contributionValue / $startValue->value) * 100 
                : 0;
            
            ComponentPerformance::create([
                'basket_performance_id' => $performance->id,
                'asset_code' => $assetCode,
                'start_weight' => $startWeight,
                'end_weight' => $endWeight,
                'average_weight' => $averageWeight,
                'contribution_value' => $contributionValue,
                'contribution_percentage' => $contributionPercentage,
                'return_value' => $returnValue,
                'return_percentage' => $returnPercentage,
            ]);
        }
    }
    
    /**
     * Get performance summary for a basket.
     */
    public function getPerformanceSummary(BasketAsset $basket): array
    {
        $performances = $basket->performances()
            ->whereIn('period_type', ['day', 'week', 'month', 'year'])
            ->orderBy('period_end', 'desc')
            ->limit(4)
            ->get();
            
        $summary = [
            'basket_code' => $basket->code,
            'basket_name' => $basket->name,
            'current_value' => $basket->latestValue?->value ?? 0,
            'performances' => [],
        ];
        
        foreach ($performances as $performance) {
            $summary['performances'][$performance->period_type] = [
                'return_percentage' => $performance->return_percentage,
                'formatted_return' => $performance->formatted_return,
                'volatility' => $performance->volatility,
                'sharpe_ratio' => $performance->sharpe_ratio,
                'performance_rating' => $performance->performance_rating,
                'risk_rating' => $performance->risk_rating,
            ];
        }
        
        return $summary;
    }
    
    /**
     * Compare basket performance against benchmarks.
     */
    public function compareToBenchmarks(BasketAsset $basket, array $benchmarkCodes): array
    {
        $comparison = [
            'basket' => $this->getPerformanceSummary($basket),
            'benchmarks' => [],
        ];
        
        foreach ($benchmarkCodes as $benchmarkCode) {
            $benchmark = BasketAsset::where('code', $benchmarkCode)->first();
            if ($benchmark) {
                $comparison['benchmarks'][$benchmarkCode] = $this->getPerformanceSummary($benchmark);
            }
        }
        
        return $comparison;
    }
    
    /**
     * Get top performing components.
     */
    public function getTopPerformers(BasketAsset $basket, string $periodType = 'month', int $limit = 5): Collection
    {
        $performance = $basket->performances()
            ->where('period_type', $periodType)
            ->orderBy('period_end', 'desc')
            ->first();
            
        if (!$performance) {
            return collect();
        }
        
        return $performance->componentPerformances()
            ->orderBy('contribution_percentage', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get worst performing components.
     */
    public function getWorstPerformers(BasketAsset $basket, string $periodType = 'month', int $limit = 5): Collection
    {
        $performance = $basket->performances()
            ->where('period_type', $periodType)
            ->orderBy('period_end', 'desc')
            ->first();
            
        if (!$performance) {
            return collect();
        }
        
        return $performance->componentPerformances()
            ->orderBy('contribution_percentage', 'asc')
            ->limit($limit)
            ->get();
    }
}