<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities\Portfolio;

use App\Domain\Treasury\Services\PerformanceTrackingService;
use App\Domain\Treasury\Services\PortfolioManagementService;
use Exception;
use Log;
use RuntimeException;
use Workflow\Activity;

class CalculatePerformanceActivity extends Activity
{
    public function __construct(
        private readonly PerformanceTrackingService $performanceService,
        private readonly PortfolioManagementService $portfolioService
    ) {
    }

    public function execute(array $input): array
    {
        $portfolioId = $input['portfolio_id'];
        $reportId = $input['report_id'];
        $period = $input['period'] ?? '30d';
        $reportType = $input['report_type'] ?? 'monthly';
        $options = $input['options'] ?? [];

        try {
            Log::info('Calculating performance metrics', [
                'portfolio_id' => $portfolioId,
                'report_id'    => $reportId,
                'period'       => $period,
                'report_type'  => $reportType,
            ]);

            // Get basic portfolio information
            $portfolio = $this->portfolioService->getPortfolio($portfolioId);

            // Calculate returns for the specified period
            $returns = $this->performanceService->calculateReturns($portfolioId, $period);

            // Get comprehensive performance metrics
            $performanceMetrics = $this->performanceService->getPerformanceMetrics($portfolioId);

            // Compare to benchmarks if requested
            $benchmarkComparison = [];
            if ($options['include_benchmarks'] ?? true) {
                $benchmarkComparison = $this->performanceService->compareToBusinessary(
                    $portfolioId,
                    $this->determineBenchmarks($portfolio, $options)
                );
            }

            // Calculate attribution analysis if requested
            $attribution = [];
            if ($options['include_attribution'] ?? false) {
                $attribution = $this->calculateAttribution($portfolioId, $period, $portfolio);
            }

            // Get risk-adjusted metrics
            $riskMetrics = $this->calculateRiskMetrics($performanceMetrics, $returns);

            // Include holdings breakdown if requested
            $holdings = [];
            if ($options['include_holdings'] ?? true) {
                $holdings = $this->getHoldingsBreakdown($portfolio);
            }

            // Create detailed performance data structure
            $performanceData = [
                'portfolio_id'   => $portfolioId,
                'report_id'      => $reportId,
                'period'         => $period,
                'report_type'    => $reportType,
                'calculated_at'  => now()->toISOString(),
                'portfolio_info' => [
                    'name'        => $portfolio['name'],
                    'total_value' => $portfolio['total_value'],
                    'strategy'    => $portfolio['strategy'],
                    'status'      => $portfolio['status'],
                ],
                'returns' => [
                    'period'            => $returns['period'],
                    'total_return'      => $returns['total_return'],
                    'annualized_return' => $returns['annualized_return'],
                    'start_value'       => $returns['start_value'],
                    'end_value'         => $returns['end_value'],
                    'return_statistics' => $returns['return_statistics'],
                ],
                'risk_metrics'        => $riskMetrics,
                'performance_metrics' => [
                    'core_metrics'          => $performanceMetrics['core_metrics'],
                    'risk_adjusted_metrics' => $performanceMetrics['risk_adjusted_metrics'],
                ],
                'benchmark_comparison' => $benchmarkComparison,
                'attribution'          => $attribution,
                'holdings'             => $holdings,
                'summary'              => [
                    'performance_grade' => $this->calculatePerformanceGrade($returns, $benchmarkComparison),
                    'key_highlights'    => $this->generateKeyHighlights($returns, $performanceMetrics, $benchmarkComparison),
                    'areas_of_concern'  => $this->identifyAreasOfConcern($returns, $performanceMetrics, $riskMetrics),
                    'recommendations'   => $this->generateRecommendations($returns, $performanceMetrics, $portfolio),
                ],
                'calculation_metadata' => [
                    'calculation_time'   => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] ?? 0,
                    'data_quality_score' => $this->assessDataQuality($returns, $performanceMetrics),
                    'options_used'       => $options,
                    'benchmark_count'    => count($benchmarkComparison['benchmarks'] ?? []),
                ],
            ];

            Log::info('Performance calculation completed', [
                'portfolio_id'      => $portfolioId,
                'report_id'         => $reportId,
                'total_return'      => $returns['total_return'],
                'annualized_return' => $returns['annualized_return'],
                'sharpe_ratio'      => $riskMetrics['sharpe_ratio'],
                'benchmark_count'   => count($benchmarkComparison['benchmarks'] ?? []),
            ]);

            return $performanceData;
        } catch (Exception $e) {
            Log::error('Performance calculation failed', [
                'portfolio_id' => $portfolioId,
                'report_id'    => $reportId,
                'period'       => $period,
                'error'        => $e->getMessage(),
            ]);

            throw new RuntimeException(
                "Failed to calculate performance for portfolio {$portfolioId}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Determine appropriate benchmarks based on portfolio strategy.
     */
    private function determineBenchmarks(array $portfolio, array $options): array
    {
        if (isset($options['benchmarks'])) {
            return $options['benchmarks'];
        }

        $strategy = $portfolio['strategy'] ?? [];
        $riskProfile = $strategy['riskProfile'] ?? 'moderate';

        return match ($riskProfile) {
            'conservative' => ['bonds', 'conservative'],
            'moderate'     => ['balanced', 'sp500'],
            'aggressive'   => ['sp500', 'aggressive'],
            'speculative'  => ['sp500', 'aggressive'],
            default        => ['balanced', 'sp500'],
        };
    }

    /**
     * Calculate attribution analysis (simplified version).
     */
    private function calculateAttribution(string $portfolioId, string $period, array $portfolio): array
    {
        // This would be a complex calculation in a real system
        // For now, we'll provide a simplified version

        $allocations = $portfolio['asset_allocations'] ?? [];
        $attribution = [];

        foreach ($allocations as $allocation) {
            $attribution[] = [
                'asset_class'          => $allocation['assetClass'] ?? 'unknown',
                'weight'               => $allocation['currentWeight'] ?? 0,
                'return'               => $this->estimateAssetClassReturn($allocation['assetClass'] ?? 'unknown', $period),
                'contribution'         => ($allocation['currentWeight'] ?? 0) * 0.01 * $this->estimateAssetClassReturn($allocation['assetClass'] ?? 'unknown', $period),
                'relative_performance' => 'neutral', // Would be calculated vs benchmark
            ];
        }

        return [
            'period'              => $period,
            'total_attribution'   => array_sum(array_column($attribution, 'contribution')),
            'asset_contributions' => $attribution,
            'top_contributor'     => $this->findTopContributor($attribution),
            'bottom_contributor'  => $this->findBottomContributor($attribution),
        ];
    }

    /**
     * Calculate comprehensive risk metrics.
     */
    private function calculateRiskMetrics(array $performanceMetrics, array $returns): array
    {
        return [
            'volatility'     => $returns['volatility'],
            'max_drawdown'   => $returns['max_drawdown'],
            'sharpe_ratio'   => $returns['sharpe_ratio'],
            'var_95'         => $performanceMetrics['risk_metrics']['var_95'] ?? 0,
            'beta'           => $performanceMetrics['core_metrics']['beta'] ?? 1,
            'tracking_error' => $performanceMetrics['return_metrics']['tracking_error'] ?? 0,
            'risk_grade'     => $this->calculateRiskGrade($returns['volatility'], $returns['max_drawdown']),
        ];
    }

    /**
     * Get holdings breakdown with weights and values.
     */
    private function getHoldingsBreakdown(array $portfolio): array
    {
        $allocations = $portfolio['asset_allocations'] ?? [];
        $totalValue = $portfolio['total_value'] ?? 0;

        $holdings = [];
        foreach ($allocations as $allocation) {
            $holdings[] = [
                'asset_class'      => $allocation['assetClass'] ?? 'unknown',
                'target_weight'    => $allocation['targetWeight'] ?? 0,
                'current_weight'   => $allocation['currentWeight'] ?? 0,
                'current_value'    => ($allocation['currentWeight'] ?? 0) * 0.01 * $totalValue,
                'drift'            => $allocation['drift'] ?? 0,
                'allocation_grade' => $this->gradeAllocation($allocation['drift'] ?? 0),
            ];
        }

        return [
            'total_value'           => $totalValue,
            'holdings'              => $holdings,
            'diversification_score' => $this->calculateDiversificationScore($holdings),
        ];
    }

    /**
     * Calculate overall performance grade.
     */
    private function calculatePerformanceGrade(array $returns, array $benchmarkComparison): string
    {
        $totalReturn = $returns['total_return'];
        $sharpeRatio = $returns['sharpe_ratio'];

        // Compare to benchmarks if available
        $benchmarkOutperformance = 0;
        if (! empty($benchmarkComparison['benchmarks'])) {
            $benchmarkOutperformance = array_sum(array_column($benchmarkComparison['benchmarks'], 'excess_return'));
            $benchmarkOutperformance /= count($benchmarkComparison['benchmarks']);
        }

        if ($totalReturn > 0.15 && $sharpeRatio > 1.5 && $benchmarkOutperformance > 0.02) {
            return 'A+';
        } elseif ($totalReturn > 0.10 && $sharpeRatio > 1.0 && $benchmarkOutperformance > 0) {
            return 'A';
        } elseif ($totalReturn > 0.05 && $sharpeRatio > 0.5) {
            return 'B';
        } elseif ($totalReturn > 0) {
            return 'C';
        } else {
            return 'D';
        }
    }

    /**
     * Generate key highlights from performance data.
     */
    private function generateKeyHighlights(array $returns, array $metrics, array $benchmarks): array
    {
        $highlights = [];

        // Return highlights
        if ($returns['total_return'] > 0.1) {
            $highlights[] = 'Strong positive returns of ' . round($returns['total_return'] * 100, 1) . '%';
        }

        // Risk-adjusted performance
        if ($returns['sharpe_ratio'] > 1.0) {
            $highlights[] = 'Excellent risk-adjusted returns (Sharpe ratio: ' . round($returns['sharpe_ratio'], 2) . ')';
        }

        // Benchmark outperformance
        if (! empty($benchmarks['benchmarks'])) {
            $outperformingCount = 0;
            foreach ($benchmarks['benchmarks'] as $benchmark) {
                if ($benchmark['excess_return'] > 0) {
                    $outperformingCount++;
                }
            }

            if ($outperformingCount > count($benchmarks['benchmarks']) / 2) {
                $highlights[] = "Outperformed {$outperformingCount} of " . count($benchmarks['benchmarks']) . ' benchmarks';
            }
        }

        // Volatility management
        if ($returns['volatility'] < 0.1) {
            $highlights[] = 'Low volatility portfolio with consistent returns';
        }

        return array_slice($highlights, 0, 3); // Limit to top 3 highlights
    }

    /**
     * Identify areas that need attention.
     */
    private function identifyAreasOfConcern(array $returns, array $metrics, array $riskMetrics): array
    {
        $concerns = [];

        // Negative returns
        if ($returns['total_return'] < 0) {
            $concerns[] = 'Negative portfolio returns of ' . round($returns['total_return'] * 100, 1) . '%';
        }

        // High volatility
        if ($riskMetrics['volatility'] > 0.2) {
            $concerns[] = 'High volatility of ' . round($riskMetrics['volatility'] * 100, 1) . '%';
        }

        // Large drawdown
        if ($riskMetrics['max_drawdown'] < -0.1) {
            $concerns[] = 'Significant maximum drawdown of ' . round(abs($riskMetrics['max_drawdown']) * 100, 1) . '%';
        }

        // Poor risk-adjusted returns
        if ($riskMetrics['sharpe_ratio'] < 0) {
            $concerns[] = 'Negative risk-adjusted returns (Sharpe ratio: ' . round($riskMetrics['sharpe_ratio'], 2) . ')';
        }

        return $concerns;
    }

    /**
     * Generate recommendations based on performance analysis.
     */
    private function generateRecommendations(array $returns, array $metrics, array $portfolio): array
    {
        $recommendations = [];

        // Rebalancing recommendation
        if ($this->portfolioService->needsRebalancing($portfolio)) {
            $recommendations[] = 'Consider rebalancing portfolio to target allocations';
        }

        // Risk management
        if ($returns['volatility'] > 0.15) {
            $recommendations[] = 'Review risk management strategies to reduce volatility';
        }

        // Performance improvement
        if ($returns['total_return'] < 0.05) {
            $recommendations[] = 'Analyze underperforming assets and consider strategy adjustments';
        }

        return $recommendations;
    }

    // Helper methods

    private function estimateAssetClassReturn(string $assetClass, string $period): float
    {
        // Simplified estimation - in reality would use actual market data
        return match ($assetClass) {
            'equities', 'stocks' => 0.08,
            'bonds'        => 0.04,
            'cash'         => 0.02,
            'alternatives' => 0.06,
            default        => 0.05,
        };
    }

    private function findTopContributor(array $attribution): array
    {
        return collect($attribution)->sortByDesc('contribution')->first() ?? [];
    }

    private function findBottomContributor(array $attribution): array
    {
        return collect($attribution)->sortBy('contribution')->first() ?? [];
    }

    private function calculateRiskGrade(float $volatility, float $maxDrawdown): string
    {
        $riskScore = ($volatility * 100) + (abs($maxDrawdown) * 50);

        return match (true) {
            $riskScore < 5  => 'Very Low',
            $riskScore < 10 => 'Low',
            $riskScore < 15 => 'Moderate',
            $riskScore < 25 => 'High',
            default         => 'Very High',
        };
    }

    private function gradeAllocation(float $drift): string
    {
        return match (true) {
            abs($drift) < 2  => 'Excellent',
            abs($drift) < 5  => 'Good',
            abs($drift) < 10 => 'Fair',
            default          => 'Poor',
        };
    }

    private function calculateDiversificationScore(array $holdings): float
    {
        if (empty($holdings)) {
            return 0.0;
        }

        // Simple diversification score based on number of holdings and weight distribution
        $weights = array_column($holdings, 'current_weight');
        $herfindahlIndex = array_sum(array_map(fn ($w) => ($w / 100) ** 2, $weights));

        return max(0, min(10, (1 - $herfindahlIndex) * 10));
    }

    private function assessDataQuality(array $returns, array $metrics): float
    {
        $score = 10.0; // Start with perfect score

        // Reduce score for missing data
        if (empty($returns['daily_returns'])) {
            $score -= 2.0;
        }

        if (($metrics['core_metrics']['totalValue'] ?? 0) <= 0) {
            $score -= 3.0;
        }

        return max(0.0, $score);
    }
}
