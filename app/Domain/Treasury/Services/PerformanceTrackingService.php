<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Services;

use App\Domain\Treasury\Aggregates\PortfolioAggregate;
use App\Domain\Treasury\Models\PortfolioEvent;
use App\Domain\Treasury\ValueObjects\PortfolioMetrics;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PerformanceTrackingService
{
    private const CACHE_TTL = 600; // 10 minutes cache for performance data

    private const RISK_FREE_RATE = 0.02; // 2% annual risk-free rate

    // Benchmark return rates (annual)
    private const BENCHMARKS = [
        'sp500'        => 0.10,
        'bonds'        => 0.04,
        'cash'         => 0.02,
        'balanced'     => 0.07,
        'conservative' => 0.05,
        'aggressive'   => 0.12,
    ];

    public function __construct(
        private readonly PortfolioManagementService $portfolioService,
        private readonly AssetValuationService $valuationService
    ) {
    }

    public function getPortfolioPerformance(string $portfolioId, string $period): array
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        $cacheKey = "portfolio_performance:{$portfolioId}:{$period}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($portfolioId, $period) {
            try {
                $returns = $this->calculateReturns($portfolioId, $period);
                $metrics = $this->getPerformanceMetrics($portfolioId);
                $benchmarkComparisons = $this->compareToBusinessary($portfolioId, ['sp500', 'balanced']);

                return [
                    'period'                => $period,
                    'returns'               => $returns,
                    'metrics'               => $metrics,
                    'benchmark_comparisons' => $benchmarkComparisons,
                    'generated_at'          => now()->toISOString(),
                ];
            } catch (Exception $e) {
                throw new RuntimeException("Failed to get portfolio performance: {$e->getMessage()}", 0, $e);
            }
        });
    }

    public function getPerformanceHistory(string $portfolioId): Collection
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        $cacheKey = "performance_history:{$portfolioId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($portfolioId) {
            return PortfolioEvent::where('aggregate_uuid', $portfolioId)
                ->where('event_class', 'App\Domain\Treasury\Events\Portfolio\PerformanceRecorded')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($event) {
                    $properties = $event->event_properties;

                    return [
                        'event_id'     => $event->id,
                        'date'         => is_string($event->created_at) ? $event->created_at : $event->created_at->toISOString(),
                        'metrics'      => $properties['metrics'] ?? [],
                        'period'       => $properties['period'] ?? 'unknown',
                        'recorded_by'  => $properties['recordedBy'] ?? 'system',
                        'total_value'  => $properties['metrics']['totalValue'] ?? 0,
                        'returns'      => $properties['metrics']['returns'] ?? 0,
                        'volatility'   => $properties['metrics']['volatility'] ?? 0,
                        'sharpe_ratio' => $properties['metrics']['sharpeRatio'] ?? 0,
                    ];
                });
        });
    }

    public function getPortfolioReports(string $portfolioId): array
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        $cacheKey = "portfolio_reports:{$portfolioId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($portfolioId) {
            try {
                // In a real implementation, this would query a reports table
                // For now, we'll return a mock structure
                return [
                    [
                        'report_id'    => Str::uuid()->toString(),
                        'portfolio_id' => $portfolioId,
                        'type'         => 'performance',
                        'period'       => '30d',
                        'format'       => 'pdf',
                        'status'       => 'completed',
                        'file_size'    => 2.5, // MB
                        'generated_at' => now()->subDays(7)->toISOString(),
                        'expires_at'   => now()->addDays(23)->toISOString(),
                        'download_url' => "/api/treasury/portfolios/{$portfolioId}/reports/download/performance-30d.pdf",
                        'metadata'     => [
                            'pages'  => 15,
                            'charts' => 8,
                            'tables' => 5,
                        ],
                    ],
                    [
                        'report_id'    => Str::uuid()->toString(),
                        'portfolio_id' => $portfolioId,
                        'type'         => 'risk_analysis',
                        'period'       => '90d',
                        'format'       => 'excel',
                        'status'       => 'completed',
                        'file_size'    => 1.2, // MB
                        'generated_at' => now()->subDays(14)->toISOString(),
                        'expires_at'   => now()->addDays(16)->toISOString(),
                        'download_url' => "/api/treasury/portfolios/{$portfolioId}/reports/download/risk-analysis-90d.xlsx",
                        'metadata'     => [
                            'worksheets'  => 6,
                            'data_points' => 2500,
                        ],
                    ],
                ];
            } catch (Exception $e) {
                throw new RuntimeException("Failed to get portfolio reports: {$e->getMessage()}", 0, $e);
            }
        });
    }

    public function calculateReturns(string $portfolioId, string $period): array
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        if (! in_array($period, ['1d', '7d', '30d', '90d', '1y', 'ytd', 'inception'])) {
            throw new InvalidArgumentException('Invalid period. Must be one of: 1d, 7d, 30d, 90d, 1y, ytd, inception');
        }

        $cacheKey = "portfolio_returns:{$portfolioId}:{$period}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($portfolioId, $period) {
            try {
                $portfolio = $this->portfolioService->getPortfolio($portfolioId);
                $currentValue = $portfolio['total_value'];

                // Get historical performance data
                $performanceHistory = $this->getPortfolioPerformanceHistory($portfolioId);

                if ($performanceHistory->isEmpty()) {
                    return $this->getDefaultReturnsStructure($period, $currentValue);
                }

                $periodStartDate = $this->getPeriodStartDate($period);
                $baselineMetrics = $this->getBaselineMetrics($performanceHistory, $periodStartDate);

                if (! $baselineMetrics) {
                    return $this->getDefaultReturnsStructure($period, $currentValue);
                }

                $baselineValue = $baselineMetrics['totalValue'];
                $totalReturn = ($currentValue - $baselineValue) / $baselineValue;
                $annualizedReturn = $this->annualizeReturn($totalReturn, $period);

                // Calculate additional metrics
                $dailyReturns = $this->calculateDailyReturns($performanceHistory, $periodStartDate);
                $volatility = $this->calculateVolatility($dailyReturns);
                $sharpeRatio = $this->calculateSharpeRatio($annualizedReturn, $volatility);
                $maxDrawdown = $this->calculateMaxDrawdown($performanceHistory, $periodStartDate);

                return [
                    'period'            => $period,
                    'start_date'        => $periodStartDate->toISOString(),
                    'end_date'          => now()->toISOString(),
                    'start_value'       => $baselineValue,
                    'end_value'         => $currentValue,
                    'total_return'      => $totalReturn,
                    'annualized_return' => $annualizedReturn,
                    'volatility'        => $volatility,
                    'sharpe_ratio'      => $sharpeRatio,
                    'max_drawdown'      => $maxDrawdown,
                    'daily_returns'     => $dailyReturns->take(30)->toArray(), // Last 30 days
                    'return_statistics' => [
                        'mean_return'   => $dailyReturns->avg(),
                        'median_return' => $this->calculateMedian($dailyReturns),
                        'positive_days' => $dailyReturns->where('>', 0)->count(),
                        'negative_days' => $dailyReturns->where('<', 0)->count(),
                        'total_days'    => $dailyReturns->count(),
                    ],
                ];
            } catch (Exception $e) {
                throw new RuntimeException("Failed to calculate returns: {$e->getMessage()}", 0, $e);
            }
        });
    }

    public function trackPerformance(string $portfolioId): void
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId);
            $currentValue = $this->valuationService->calculatePortfolioValue($portfolioId);

            // Calculate current performance metrics
            $returns = $this->calculateReturns($portfolioId, '1y');

            $metrics = new PortfolioMetrics(
                $currentValue,
                $returns['annualized_return'],
                $returns['sharpe_ratio'],
                $returns['volatility'],
                $returns['max_drawdown'],
                $this->calculateAlpha($portfolioId, $returns['annualized_return']),
                $this->calculateBeta($portfolioId),
                [
                    'tracking_date'    => now()->toISOString(),
                    'period'           => '1y',
                    'benchmark_return' => $this->getBenchmarkReturn($portfolio['strategy']),
                ]
            );

            // Record performance in aggregate
            $aggregate = PortfolioAggregate::retrieve($portfolioId);
            $aggregate->recordPerformance(
                Str::uuid()->toString(),
                $metrics,
                'annual',
                'system'
            );

            $aggregate->persist();

            // Clear performance caches
            $this->clearPerformanceCache($portfolioId);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to track performance: {$e->getMessage()}", 0, $e);
        }
    }

    public function getPerformanceMetrics(string $portfolioId): array
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        $cacheKey = "performance_metrics:{$portfolioId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($portfolioId) {
            try {
                $portfolio = $this->portfolioService->getPortfolio($portfolioId);
                $latestMetrics = $portfolio['latest_metrics'];

                if (empty($latestMetrics)) {
                    return $this->getDefaultMetricsStructure();
                }

                // Calculate additional derived metrics
                $informationRatio = $this->calculateInformationRatio($portfolioId, $latestMetrics);
                $treynorRatio = $this->calculateTreynorRatio($latestMetrics);
                $calmarRatio = $this->calculateCalmarRatio($latestMetrics);
                $sortinoRatio = $this->calculateSortinoRatio($portfolioId);

                return [
                    'core_metrics'          => $latestMetrics,
                    'risk_adjusted_metrics' => [
                        'sharpe_ratio'      => $latestMetrics['sharpeRatio'],
                        'information_ratio' => $informationRatio,
                        'treynor_ratio'     => $treynorRatio,
                        'calmar_ratio'      => $calmarRatio,
                        'sortino_ratio'     => $sortinoRatio,
                    ],
                    'risk_metrics' => [
                        'volatility'   => $latestMetrics['volatility'],
                        'max_drawdown' => $latestMetrics['maxDrawdown'],
                        'beta'         => $latestMetrics['beta'],
                        'var_95'       => $this->calculateVaR($portfolioId, 0.95),
                        'cvar_95'      => $this->calculateCVaR($portfolioId, 0.95),
                    ],
                    'return_metrics' => [
                        'total_return'   => $latestMetrics['returns'],
                        'alpha'          => $latestMetrics['alpha'],
                        'excess_return'  => $latestMetrics['returns'] - self::RISK_FREE_RATE,
                        'tracking_error' => $this->calculateTrackingError($portfolioId),
                    ],
                    'benchmark_comparison' => $this->compareToBusinessary($portfolioId, ['sp500', 'balanced']),
                ];
            } catch (Exception $e) {
                throw new RuntimeException("Failed to get performance metrics: {$e->getMessage()}", 0, $e);
            }
        });
    }

    public function compareToBusinessary(string $portfolioId, array $benchmarks): array
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        if (empty($benchmarks)) {
            throw new InvalidArgumentException('Benchmarks cannot be empty');
        }

        try {
            $portfolioReturns = $this->calculateReturns($portfolioId, '1y');
            $portfolioReturn = $portfolioReturns['annualized_return'];
            $portfolioVolatility = $portfolioReturns['volatility'];
            $portfolioSharpe = $portfolioReturns['sharpe_ratio'];

            $comparisons = [];

            foreach ($benchmarks as $benchmark) {
                if (! array_key_exists($benchmark, self::BENCHMARKS)) {
                    continue;
                }

                $benchmarkReturn = self::BENCHMARKS[$benchmark];
                $benchmarkVolatility = $this->getBenchmarkVolatility($benchmark);
                $benchmarkSharpe = ($benchmarkReturn - self::RISK_FREE_RATE) / $benchmarkVolatility;

                $comparisons[$benchmark] = [
                    'name'                      => $this->getBenchmarkName($benchmark),
                    'benchmark_return'          => $benchmarkReturn,
                    'portfolio_return'          => $portfolioReturn,
                    'excess_return'             => $portfolioReturn - $benchmarkReturn,
                    'benchmark_volatility'      => $benchmarkVolatility,
                    'portfolio_volatility'      => $portfolioVolatility,
                    'benchmark_sharpe'          => $benchmarkSharpe,
                    'portfolio_sharpe'          => $portfolioSharpe,
                    'relative_performance'      => $this->getRelativePerformance($portfolioReturn, $benchmarkReturn),
                    'risk_adjusted_performance' => $this->getRiskAdjustedPerformance($portfolioSharpe, $benchmarkSharpe),
                ];
            }

            return [
                'portfolio_id'      => $portfolioId,
                'comparison_period' => '1y',
                'comparison_date'   => now()->toISOString(),
                'benchmarks'        => $comparisons,
                'summary'           => $this->generateComparisonSummary($comparisons),
            ];
        } catch (Exception $e) {
            throw new RuntimeException("Failed to compare to benchmarks: {$e->getMessage()}", 0, $e);
        }
    }

    private function getPortfolioPerformanceHistory(string $portfolioId): Collection
    {
        return PortfolioEvent::where('aggregate_uuid', $portfolioId)
            ->where('event_class', 'App\Domain\Treasury\Events\Portfolio\PerformanceRecorded')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($event) {
                $properties = $event->event_properties;

                return [
                    'date'       => $event->created_at,
                    'totalValue' => $properties['metrics']['totalValue'] ?? 0,
                    'returns'    => $properties['metrics']['returns'] ?? 0,
                    'volatility' => $properties['metrics']['volatility'] ?? 0,
                ];
            });
    }

    private function getPeriodStartDate(string $period): \Carbon\Carbon
    {
        return match ($period) {
            '1d'        => now()->subDay(),
            '7d'        => now()->subWeek(),
            '30d'       => now()->subMonth(),
            '90d'       => now()->subMonths(3),
            '1y'        => now()->subYear(),
            'ytd'       => now()->startOfYear(),
            'inception' => now()->subYears(10), // Fallback
            default     => now()->subMonth(),
        };
    }

    private function getBaselineMetrics(Collection $history, \Carbon\Carbon $startDate): ?array
    {
        return $history->where('date', '>=', $startDate)->first();
    }

    private function calculateDailyReturns(Collection $history, \Carbon\Carbon $startDate): Collection
    {
        $periodHistory = $history->where('date', '>=', $startDate);
        $returns = collect();

        $previousValue = null;
        foreach ($periodHistory as $record) {
            if ($previousValue !== null && $previousValue > 0) {
                $dailyReturn = ($record['totalValue'] - $previousValue) / $previousValue;
                $returns->push($dailyReturn);
            }
            $previousValue = $record['totalValue'];
        }

        return $returns;
    }

    private function calculateVolatility(Collection $returns): float
    {
        if ($returns->count() < 2) {
            return 0.0;
        }

        $mean = $returns->avg();
        $sumSquaredDeviations = $returns->sum(fn ($return) => pow($return - $mean, 2));
        $variance = $sumSquaredDeviations / ($returns->count() - 1);

        return sqrt($variance * 252); // Annualized volatility
    }

    private function calculateSharpeRatio(float $return, float $volatility): float
    {
        return $volatility > 0 ? ($return - self::RISK_FREE_RATE) / $volatility : 0.0;
    }

    private function calculateMaxDrawdown(Collection $history, \Carbon\Carbon $startDate): float
    {
        $periodHistory = $history->where('date', '>=', $startDate);
        $maxDrawdown = 0.0;
        $peak = 0.0;

        foreach ($periodHistory as $record) {
            $value = $record['totalValue'];
            $peak = max($peak, $value);

            if ($peak > 0) {
                $drawdown = ($value - $peak) / $peak;
                $maxDrawdown = min($maxDrawdown, $drawdown);
            }
        }

        return $maxDrawdown;
    }

    private function calculateAlpha(string $portfolioId, float $portfolioReturn): float
    {
        // Simplified alpha calculation against market benchmark
        $marketReturn = self::BENCHMARKS['sp500'];
        $beta = $this->calculateBeta($portfolioId);

        return $portfolioReturn - (self::RISK_FREE_RATE + $beta * ($marketReturn - self::RISK_FREE_RATE));
    }

    private function calculateBeta(string $portfolioId): float
    {
        // Simplified beta calculation - in reality would use correlation analysis
        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId);
            $equityWeight = 0.0;

            foreach ($portfolio['asset_allocations'] as $allocation) {
                if (in_array($allocation['assetClass'], ['equities', 'stocks'])) {
                    $equityWeight += $allocation['currentWeight'] / 100;
                }
            }

            return min($equityWeight * 1.2, 2.0); // Simplified beta estimate
        } catch (Exception) {
            return 1.0; // Default beta
        }
    }

    private function annualizeReturn(float $totalReturn, string $period): float
    {
        $days = match ($period) {
            '1d'    => 1,
            '7d'    => 7,
            '30d'   => 30,
            '90d'   => 90,
            '1y'    => 365,
            'ytd'   => now()->dayOfYear,
            default => 365,
        };

        $divisor = is_numeric($days) ? (float) $days : 365.0;

        return pow(1 + $totalReturn, 365.0 / $divisor) - 1;
    }

    private function calculateMedian(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return 0.0;
        }

        if ($count % 2 === 0) {
            return ($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2;
        }

        return $sorted[intval($count / 2)];
    }

    private function getDefaultReturnsStructure(string $period, float $currentValue): array
    {
        return [
            'period'            => $period,
            'start_date'        => $this->getPeriodStartDate($period)->toISOString(),
            'end_date'          => now()->toISOString(),
            'start_value'       => $currentValue,
            'end_value'         => $currentValue,
            'total_return'      => 0.0,
            'annualized_return' => 0.0,
            'volatility'        => 0.0,
            'sharpe_ratio'      => 0.0,
            'max_drawdown'      => 0.0,
            'daily_returns'     => [],
            'return_statistics' => [
                'mean_return'   => 0.0,
                'median_return' => 0.0,
                'positive_days' => 0,
                'negative_days' => 0,
                'total_days'    => 0,
            ],
        ];
    }

    private function getDefaultMetricsStructure(): array
    {
        return [
            'core_metrics' => [
                'totalValue'  => 0.0,
                'returns'     => 0.0,
                'sharpeRatio' => 0.0,
                'volatility'  => 0.0,
                'maxDrawdown' => 0.0,
                'alpha'       => 0.0,
                'beta'        => 1.0,
            ],
            'risk_adjusted_metrics' => [
                'sharpe_ratio'      => 0.0,
                'information_ratio' => 0.0,
                'treynor_ratio'     => 0.0,
                'calmar_ratio'      => 0.0,
                'sortino_ratio'     => 0.0,
            ],
            'risk_metrics' => [
                'volatility'   => 0.0,
                'max_drawdown' => 0.0,
                'beta'         => 1.0,
                'var_95'       => 0.0,
                'cvar_95'      => 0.0,
            ],
            'return_metrics' => [
                'total_return'   => 0.0,
                'alpha'          => 0.0,
                'excess_return'  => 0.0,
                'tracking_error' => 0.0,
            ],
            'benchmark_comparison' => [],
        ];
    }

    private function getBenchmarkReturn(array $strategy): float
    {
        $riskProfile = $strategy['riskProfile'] ?? 'moderate';

        return match ($riskProfile) {
            'conservative' => self::BENCHMARKS['conservative'],
            'moderate'     => self::BENCHMARKS['balanced'],
            'aggressive'   => self::BENCHMARKS['aggressive'],
            'speculative'  => self::BENCHMARKS['aggressive'],
            default        => self::BENCHMARKS['balanced'],
        };
    }

    private function getBenchmarkVolatility(string $benchmark): float
    {
        return match ($benchmark) {
            'sp500'        => 0.16,
            'bonds'        => 0.04,
            'cash'         => 0.01,
            'balanced'     => 0.10,
            'conservative' => 0.06,
            'aggressive'   => 0.18,
            default        => 0.10,
        };
    }

    private function getBenchmarkName(string $benchmark): string
    {
        return match ($benchmark) {
            'sp500'        => 'S&P 500',
            'bonds'        => 'Bond Index',
            'cash'         => 'Cash/Money Market',
            'balanced'     => 'Balanced Portfolio',
            'conservative' => 'Conservative Portfolio',
            'aggressive'   => 'Aggressive Growth',
            default        => ucfirst($benchmark),
        };
    }

    private function calculateInformationRatio(string $portfolioId, array $metrics): float
    {
        // Simplified calculation
        $trackingError = $this->calculateTrackingError($portfolioId);

        return $trackingError > 0 ? $metrics['alpha'] / $trackingError : 0.0;
    }

    private function calculateTreynorRatio(array $metrics): float
    {
        return $metrics['beta'] > 0 ? ($metrics['returns'] - self::RISK_FREE_RATE) / $metrics['beta'] : 0.0;
    }

    private function calculateCalmarRatio(array $metrics): float
    {
        return $metrics['maxDrawdown'] < 0 ? $metrics['returns'] / abs($metrics['maxDrawdown']) : 0.0;
    }

    private function calculateSortinoRatio(string $portfolioId): float
    {
        // Simplified - would need downside deviation calculation
        return 0.0;
    }

    private function calculateVaR(string $portfolioId, float $confidence): float
    {
        // Simplified VaR calculation - would need more sophisticated modeling
        return 0.0;
    }

    private function calculateCVaR(string $portfolioId, float $confidence): float
    {
        // Simplified CVaR calculation - would need more sophisticated modeling
        return 0.0;
    }

    private function calculateTrackingError(string $portfolioId): float
    {
        // Simplified tracking error calculation
        return 0.02; // 2% default tracking error
    }

    private function getRelativePerformance(float $portfolioReturn, float $benchmarkReturn): string
    {
        $diff = $portfolioReturn - $benchmarkReturn;

        if ($diff > 0.02) {
            return 'significantly_outperformed';
        }
        if ($diff > 0.005) {
            return 'outperformed';
        }
        if ($diff > -0.005) {
            return 'matched';
        }
        if ($diff > -0.02) {
            return 'underperformed';
        }

        return 'significantly_underperformed';
    }

    private function getRiskAdjustedPerformance(float $portfolioSharpe, float $benchmarkSharpe): string
    {
        $diff = $portfolioSharpe - $benchmarkSharpe;

        if ($diff > 0.2) {
            return 'superior';
        }
        if ($diff > 0.05) {
            return 'better';
        }
        if ($diff > -0.05) {
            return 'similar';
        }
        if ($diff > -0.2) {
            return 'worse';
        }

        return 'poor';
    }

    private function generateComparisonSummary(array $comparisons): array
    {
        $outperformed = 0;
        $totalComparisons = count($comparisons);

        foreach ($comparisons as $comparison) {
            if ($comparison['excess_return'] > 0) {
                $outperformed++;
            }
        }

        return [
            'total_benchmarks'        => $totalComparisons,
            'outperformed_count'      => $outperformed,
            'outperformed_percentage' => $totalComparisons > 0 ? ($outperformed / $totalComparisons) * 100 : 0,
            'overall_assessment'      => $outperformed >= $totalComparisons / 2 ? 'positive' : 'negative',
        ];
    }

    private function clearPerformanceCache(string $portfolioId): void
    {
        $patterns = [
            "portfolio_returns:{$portfolioId}:*",
            "performance_metrics:{$portfolioId}",
            "portfolio_performance:{$portfolioId}:*",
            "performance_history:{$portfolioId}",
            "portfolio_reports:{$portfolioId}",
        ];

        foreach ($patterns as $pattern) {
            // In production, would use proper cache tag clearing
            Cache::forget(str_replace('*', '1d', $pattern));
            Cache::forget(str_replace('*', '7d', $pattern));
            Cache::forget(str_replace('*', '30d', $pattern));
            Cache::forget(str_replace('*', '90d', $pattern));
            Cache::forget(str_replace('*', '1y', $pattern));
            Cache::forget(str_replace('*', 'ytd', $pattern));
            Cache::forget(str_replace('*', 'inception', $pattern));
        }
    }
}
