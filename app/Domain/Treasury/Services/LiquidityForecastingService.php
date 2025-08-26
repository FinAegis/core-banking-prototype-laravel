<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Treasury\Aggregates\TreasuryAggregate;
use App\Domain\Treasury\Events\LiquidityForecastGenerated;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service for forecasting liquidity and cash flow management.
 *
 * Provides advanced predictive analytics for treasury operations including:
 * - Cash flow prediction using historical patterns
 * - Liquidity risk metrics calculation
 * - Stress testing scenarios
 * - Early warning indicators
 */
class LiquidityForecastingService
{
    private const CACHE_TTL = 3600; // 1 hour cache

    private const MIN_DATA_POINTS = 30; // Minimum historical data for reliable forecast

    /**
     * Generate comprehensive liquidity forecast.
     */
    public function generateForecast(
        string $treasuryId,
        int $forecastDays = 30,
        array $scenarios = []
    ): array {
        $cacheKey = "liquidity_forecast:{$treasuryId}:{$forecastDays}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($treasuryId, $forecastDays, $scenarios) {
            // Gather historical data
            $historicalData = $this->gatherHistoricalData($treasuryId);

            // Validate sufficient data
            if ($historicalData->count() < self::MIN_DATA_POINTS) {
                return $this->generateBasicForecast($treasuryId, $forecastDays);
            }

            // Analyze patterns
            $patterns = $this->analyzePatterns($historicalData);

            // Generate base forecast
            $baseForecast = $this->projectCashFlows(
                $historicalData,
                $patterns,
                $forecastDays
            );

            // Apply scenario analysis
            $scenarioResults = $this->runScenarioAnalysis(
                $baseForecast,
                $scenarios ?: $this->getDefaultScenarios()
            );

            // Calculate risk metrics
            $riskMetrics = $this->calculateLiquidityRiskMetrics(
                $baseForecast,
                $scenarioResults
            );

            // Generate alerts
            $alerts = $this->generateLiquidityAlerts($riskMetrics, $baseForecast);

            // Store forecast in aggregate
            $this->storeForecastInAggregate($treasuryId, $baseForecast, $riskMetrics);

            return [
                'treasury_id'      => $treasuryId,
                'forecast_period'  => $forecastDays,
                'generated_at'     => now()->format('Y-m-d\TH:i:s.uP'),
                'base_forecast'    => $baseForecast,
                'scenarios'        => $scenarioResults,
                'risk_metrics'     => $riskMetrics,
                'alerts'           => $alerts,
                'confidence_level' => $this->calculateConfidenceLevel($historicalData, $patterns),
                'recommendations'  => $this->generateRecommendations($riskMetrics, $alerts),
            ];
        });
    }

    /**
     * Calculate real-time liquidity position.
     */
    public function calculateCurrentLiquidity(string $treasuryId): array
    {
        $accounts = $this->getTreasuryAccounts($treasuryId);

        // Calculate available liquidity
        $availableLiquidity = $accounts->sum(function ($account) {
            return $account->available_balance;
        });

        // Calculate committed outflows (next 24 hours)
        $committedOutflows = $this->getCommittedOutflows($treasuryId, 1);

        // Calculate expected inflows (next 24 hours)
        $expectedInflows = $this->getExpectedInflows($treasuryId, 1);

        // Calculate net position
        $netPosition = $availableLiquidity + $expectedInflows - $committedOutflows;

        // Calculate coverage ratios
        $coverageRatio = $committedOutflows > 0
            ? $availableLiquidity / $committedOutflows
            : PHP_FLOAT_MAX;

        return [
            'timestamp'              => now()->toIso8601String(),
            'available_liquidity'    => (float) $availableLiquidity,
            'committed_outflows_24h' => (float) $committedOutflows,
            'expected_inflows_24h'   => (float) $expectedInflows,
            'net_position_24h'       => (float) $netPosition,
            'coverage_ratio'         => min((float) $coverageRatio, 100.0), // Cap at 100x for display
            'status'                 => $this->determineLiquidityStatus($coverageRatio),
            'buffer_days'            => $this->calculateBufferDays($availableLiquidity, $treasuryId),
        ];
    }

    /**
     * Project cash flows based on historical patterns.
     */
    private function projectCashFlows(
        Collection $historicalData,
        array $patterns,
        int $days
    ): array {
        $projections = [];
        $currentDate = Carbon::now();
        $currentBalance = $this->getCurrentBalance($historicalData);

        for ($day = 1; $day <= $days; $day++) {
            $projectionDate = $currentDate->copy()->addDays($day);

            // Apply seasonal patterns
            $seasonalFactor = $this->getSeasonalFactor($projectionDate, $patterns);

            // Apply day-of-week patterns
            $dayOfWeekFactor = $this->getDayOfWeekFactor($projectionDate, $patterns);

            // Apply trend
            $trend = $patterns['trend'] ?? 0;

            // Calculate projected flows
            $baseInflow = $patterns['avg_daily_inflow'] ?? 0;
            $baseOutflow = $patterns['avg_daily_outflow'] ?? 0;

            $projectedInflow = $baseInflow * $seasonalFactor * $dayOfWeekFactor * (1 + $trend * $day / 365);
            $projectedOutflow = $baseOutflow * $seasonalFactor * $dayOfWeekFactor;

            // Add volatility
            $inflowVolatility = $this->generateVolatility($patterns['inflow_volatility'] ?? 0.1);
            $outflowVolatility = $this->generateVolatility($patterns['outflow_volatility'] ?? 0.1);

            $projectedInflow *= (1 + $inflowVolatility);
            $projectedOutflow *= (1 + $outflowVolatility);

            // Calculate running balance
            $netFlow = $projectedInflow - $projectedOutflow;
            $currentBalance += $netFlow;

            $projections[] = [
                'date'                => $projectionDate->format('Y-m-d'),
                'day_number'          => $day,
                'projected_inflow'    => round($projectedInflow, 2),
                'projected_outflow'   => round($projectedOutflow, 2),
                'net_flow'            => round($netFlow, 2),
                'projected_balance'   => round($currentBalance, 2),
                'confidence_interval' => [
                    'lower' => round($currentBalance * 0.8, 2),
                    'upper' => round($currentBalance * 1.2, 2),
                ],
            ];
        }

        return $projections;
    }

    /**
     * Analyze historical patterns for forecasting.
     */
    private function analyzePatterns(Collection $historicalData): array
    {
        $patterns = [
            'avg_daily_inflow'     => 0,
            'avg_daily_outflow'    => 0,
            'inflow_volatility'    => 0,
            'outflow_volatility'   => 0,
            'seasonal_patterns'    => [],
            'day_of_week_patterns' => [],
            'trend'                => 0,
            'cyclicality'          => [],
        ];

        // Group by date
        $dailyData = $historicalData->groupBy(function ($item) {
            return Carbon::parse($item->created_at)->format('Y-m-d');
        });

        // Calculate daily aggregates
        $dailyFlows = $dailyData->map(function ($dayTransactions) {
            return [
                'inflow'  => $dayTransactions->where('type', 'credit')->sum('amount'),
                'outflow' => abs($dayTransactions->where('type', 'debit')->sum('amount')),
                'net'     => $dayTransactions->sum(function ($t) {
                    return $t->type === 'credit' ? $t->amount : -$t->amount;
                }),
            ];
        });

        // Calculate averages
        $patterns['avg_daily_inflow'] = $dailyFlows->avg('inflow');
        $patterns['avg_daily_outflow'] = $dailyFlows->avg('outflow');

        // Calculate volatility (standard deviation)
        $patterns['inflow_volatility'] = $this->calculateVolatility($dailyFlows->pluck('inflow'));
        $patterns['outflow_volatility'] = $this->calculateVolatility($dailyFlows->pluck('outflow'));

        // Analyze day of week patterns
        $patterns['day_of_week_patterns'] = $this->analyzeDayOfWeekPatterns($historicalData);

        // Analyze monthly seasonal patterns
        $patterns['seasonal_patterns'] = $this->analyzeSeasonalPatterns($historicalData);

        // Calculate trend using linear regression
        $patterns['trend'] = $this->calculateTrend($dailyFlows);

        // Detect cyclical patterns
        $patterns['cyclicality'] = $this->detectCyclicalPatterns($dailyFlows);

        return $patterns;
    }

    /**
     * Calculate liquidity risk metrics.
     */
    private function calculateLiquidityRiskMetrics(
        array $baseForecast,
        array $scenarioResults
    ): array {
        $metrics = [
            'liquidity_coverage_ratio'  => 0,
            'net_stable_funding_ratio'  => 0,
            'stress_test_survival_days' => 0,
            'probability_of_shortage'   => 0,
            'value_at_risk_95'          => 0,
            'expected_shortfall'        => 0,
            'liquidity_buffer_adequacy' => 0,
        ];

        // Calculate Liquidity Coverage Ratio (LCR)
        $metrics['liquidity_coverage_ratio'] = $this->calculateLCR($baseForecast);

        // Calculate Net Stable Funding Ratio (NSFR)
        $metrics['net_stable_funding_ratio'] = $this->calculateNSFR($baseForecast);

        // Stress test survival days
        $stressScenario = $scenarioResults['severe_stress'] ?? null;
        if ($stressScenario) {
            $metrics['stress_test_survival_days'] = $this->calculateSurvivalDays($stressScenario);
        }

        // Probability of shortage
        $metrics['probability_of_shortage'] = $this->calculateShortProbability($baseForecast, $scenarioResults);

        // Value at Risk (95th percentile)
        $metrics['value_at_risk_95'] = $this->calculateVaR($baseForecast, 0.95);

        // Expected Shortfall
        $metrics['expected_shortfall'] = $this->calculateExpectedShortfall($baseForecast);

        // Liquidity buffer adequacy
        $metrics['liquidity_buffer_adequacy'] = $this->assessBufferAdequacy($baseForecast);

        return $metrics;
    }

    /**
     * Run scenario analysis on forecast.
     */
    private function runScenarioAnalysis(array $baseForecast, array $scenarios): array
    {
        $results = [];

        foreach ($scenarios as $scenarioName => $scenario) {
            $adjustedForecast = $this->applyScenario($baseForecast, $scenario);

            $results[$scenarioName] = [
                'description'          => $scenario['description'] ?? '',
                'impact_factors'       => $scenario,
                'adjusted_forecast'    => $adjustedForecast,
                'minimum_balance'      => ! empty($adjustedForecast) ? min(array_column($adjustedForecast, 'projected_balance')) : 0.0,
                'days_below_threshold' => $this->countDaysBelowThreshold($adjustedForecast),
                'recovery_time'        => $this->estimateRecoveryTime($adjustedForecast),
            ];
        }

        return $results;
    }

    /**
     * Generate liquidity alerts based on metrics.
     */
    private function generateLiquidityAlerts(array $riskMetrics, array $forecast): array
    {
        $alerts = [];

        // Check LCR threshold
        if ($riskMetrics['liquidity_coverage_ratio'] < 1.0) {
            $alerts[] = [
                'level'           => 'critical',
                'type'            => 'lcr_breach',
                'message'         => 'Liquidity Coverage Ratio below regulatory minimum',
                'value'           => $riskMetrics['liquidity_coverage_ratio'],
                'threshold'       => 1.0,
                'action_required' => true,
            ];
        }

        // Check stress survival
        if ($riskMetrics['stress_test_survival_days'] < 30) {
            $alerts[] = [
                'level'           => 'warning',
                'type'            => 'stress_resilience',
                'message'         => 'Limited resilience under stress scenario',
                'value'           => $riskMetrics['stress_test_survival_days'],
                'threshold'       => 30,
                'action_required' => true,
            ];
        }

        // Check shortage probability
        if ($riskMetrics['probability_of_shortage'] > 0.05) {
            $alerts[] = [
                'level'           => 'warning',
                'type'            => 'shortage_risk',
                'message'         => 'Elevated probability of liquidity shortage',
                'value'           => $riskMetrics['probability_of_shortage'],
                'threshold'       => 0.05,
                'action_required' => false,
            ];
        }

        // Check for negative balance projections
        $negativeBalances = array_filter($forecast, fn ($day) => $day['projected_balance'] < 0);
        if (! empty($negativeBalances)) {
            $firstNegativeDay = reset($negativeBalances);
            $alerts[] = [
                'level'           => 'critical',
                'type'            => 'negative_balance',
                'message'         => "Negative balance projected on day {$firstNegativeDay['day_number']}",
                'value'           => $firstNegativeDay['projected_balance'],
                'date'            => $firstNegativeDay['date'],
                'action_required' => true,
            ];
        }

        return $alerts;
    }

    /**
     * Generate recommendations based on analysis.
     */
    private function generateRecommendations(array $riskMetrics, array $alerts): array
    {
        $recommendations = [];

        // LCR recommendations
        if ($riskMetrics['liquidity_coverage_ratio'] < 1.2) {
            $recommendations[] = [
                'priority'        => 'high',
                'category'        => 'liquidity_management',
                'action'          => 'Increase high-quality liquid assets',
                'rationale'       => 'LCR approaching regulatory minimum',
                'expected_impact' => 'Improve LCR by 15-20%',
            ];
        }

        // Stress resilience recommendations
        if ($riskMetrics['stress_test_survival_days'] < 45) {
            $recommendations[] = [
                'priority'        => 'medium',
                'category'        => 'contingency_planning',
                'action'          => 'Establish additional credit facilities',
                'rationale'       => 'Limited stress scenario survival period',
                'expected_impact' => 'Extend survival by 15-30 days',
            ];
        }

        // VaR recommendations
        if ($riskMetrics['value_at_risk_95'] > 0.1) {
            $recommendations[] = [
                'priority'        => 'medium',
                'category'        => 'risk_management',
                'action'          => 'Implement cash flow hedging strategies',
                'rationale'       => 'High cash flow volatility detected',
                'expected_impact' => 'Reduce VaR by 30-40%',
            ];
        }

        // Alert-based recommendations
        foreach ($alerts as $alert) {
            if ($alert['level'] === 'critical' && $alert['action_required']) {
                $recommendations[] = [
                    'priority'        => 'urgent',
                    'category'        => 'immediate_action',
                    'action'          => $this->getAlertRecommendation($alert),
                    'rationale'       => $alert['message'],
                    'expected_impact' => 'Resolve critical issue',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Helper method to get alert-specific recommendations.
     */
    private function getAlertRecommendation(array $alert): string
    {
        return match ($alert['type']) {
            'lcr_breach'        => 'Immediately liquidate non-essential investments or secure emergency funding',
            'negative_balance'  => 'Accelerate receivables collection and defer non-critical payments',
            'stress_resilience' => 'Negotiate standby credit facilities with banking partners',
            default             => 'Review and adjust liquidity management strategy',
        };
    }

    /**
     * Store forecast in Treasury aggregate for event sourcing.
     */
    private function storeForecastInAggregate(
        string $treasuryId,
        array $forecast,
        array $riskMetrics
    ): void {
        $aggregate = TreasuryAggregate::retrieve($treasuryId);

        // Record forecast generation event
        $aggregate->recordThat(new LiquidityForecastGenerated(
            aggregateRootUuid: $treasuryId,
            forecast: $forecast,
            riskMetrics: $riskMetrics,
            generatedAt: now(),
            generatedBy: 'system'
        ));

        $aggregate->persist();
    }

    /**
     * Calculate standard deviation for volatility measurement.
     */
    private function calculateVolatility(Collection $values): float
    {
        if ($values->isEmpty()) {
            return 0;
        }

        $mean = $values->avg();
        $variance = $values->map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        })->avg();

        return sqrt($variance) / max($mean, 1);
    }

    /**
     * Calculate linear trend using simple regression.
     */
    private function calculateTrend(Collection $dailyFlows): float
    {
        if ($dailyFlows->count() < 2) {
            return 0;
        }

        $x = range(1, $dailyFlows->count());
        $y = $dailyFlows->pluck('net')->values()->toArray();

        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator == 0) {
            return 0;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;

        // Normalize trend to annual percentage
        return ($slope * 365) / max(abs($sumY / $n), 1);
    }

    /**
     * Helper methods for pattern analysis.
     */
    private function analyzeDayOfWeekPatterns(Collection $data): array
    {
        $patterns = [];
        for ($day = 0; $day <= 6; $day++) {
            $dayData = $data->filter(function ($item) use ($day) {
                return Carbon::parse($item->created_at)->dayOfWeek === $day;
            });

            $patterns[$day] = $dayData->isNotEmpty()
                ? $dayData->avg('amount')
                : 1.0;
        }

        // Normalize to factors
        $avg = array_sum($patterns) / 7;
        foreach ($patterns as $day => $value) {
            $patterns[$day] = $avg > 0 ? $value / $avg : 1.0;
        }

        return $patterns;
    }

    private function analyzeSeasonalPatterns(Collection $data): array
    {
        $patterns = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $data->filter(function ($item) use ($month) {
                return Carbon::parse($item->created_at)->month === $month;
            });

            $patterns[$month] = $monthData->isNotEmpty()
                ? $monthData->avg('amount')
                : 1.0;
        }

        // Normalize
        $avg = array_sum($patterns) / 12;
        foreach ($patterns as $month => $value) {
            $patterns[$month] = $avg > 0 ? $value / $avg : 1.0;
        }

        return $patterns;
    }

    private function detectCyclicalPatterns(Collection $dailyFlows): array
    {
        // Simplified cyclical pattern detection
        // In production, would use FFT or wavelet analysis
        return [
            'weekly_cycle'    => true,
            'monthly_cycle'   => true,
            'quarterly_cycle' => false,
        ];
    }

    /**
     * Helper methods for metrics calculation.
     */
    private function calculateLCR(array $forecast): float
    {
        // Simplified LCR calculation
        // Real implementation would follow Basel III standards
        $hqla = $forecast[0]['projected_balance'] ?? 0; // High Quality Liquid Assets
        $netOutflows30Days = 0;

        for ($i = 0; $i < min(30, count($forecast)); $i++) {
            $netOutflows30Days += max(0, $forecast[$i]['projected_outflow'] - $forecast[$i]['projected_inflow'] * 0.75);
        }

        return $netOutflows30Days > 0 ? $hqla / $netOutflows30Days : PHP_FLOAT_MAX;
    }

    private function calculateNSFR(array $forecast): float
    {
        // Simplified NSFR calculation
        // Real implementation would categorize funding sources and requirements
        return 1.15; // Placeholder
    }

    private function calculateSurvivalDays(array $stressScenario): int
    {
        $days = 0;
        foreach ($stressScenario['adjusted_forecast'] as $day) {
            if ($day['projected_balance'] < 0) {
                break;
            }
            $days++;
        }

        return $days;
    }

    private function calculateShortProbability(array $forecast, array $scenarios): float
    {
        $shortageCount = 0;
        $totalScenarios = count($scenarios) + 1; // Include base case

        // Check base case
        $balances = array_column($forecast, 'projected_balance');
        if (! empty($balances) && min($balances) < 0) {
            $shortageCount++;
        }

        // Check scenarios
        foreach ($scenarios as $scenario) {
            if (isset($scenario['adjusted_forecast'])) {
                $scenarioBalances = array_column($scenario['adjusted_forecast'], 'projected_balance');
                if (! empty($scenarioBalances) && min($scenarioBalances) < 0) {
                    $shortageCount++;
                }
            }
        }

        return $shortageCount / $totalScenarios;
    }

    private function calculateVaR(array $forecast, float $confidence): float
    {
        $balances = array_column($forecast, 'projected_balance');
        sort($balances);
        $index = (int) ((1 - $confidence) * count($balances));

        return abs($balances[$index] ?? 0);
    }

    private function calculateExpectedShortfall(array $forecast): float
    {
        $negativeBalances = array_filter(
            array_column($forecast, 'projected_balance'),
            fn ($balance) => $balance < 0
        );

        return empty($negativeBalances) ? 0 : abs(array_sum($negativeBalances) / count($negativeBalances));
    }

    private function assessBufferAdequacy(array $forecast): float
    {
        $balances = array_column($forecast, 'projected_balance');
        $minBalance = ! empty($balances) ? min($balances) : 0.0;
        $avgBalance = array_sum(array_column($forecast, 'projected_balance')) / count($forecast);

        return $avgBalance > 0 ? max(0, $minBalance / $avgBalance) : 0;
    }

    /**
     * Utility methods.
     */
    private function gatherHistoricalData(string $treasuryId): Collection
    {
        return Transaction::query()
            ->whereHas('account', function ($query) use ($treasuryId) {
                /** @phpstan-ignore-next-line */
                $query->where('treasury_id', $treasuryId);
            })
            ->where('created_at', '>=', now()->subMonths(6))
            ->orderBy('created_at')
            ->get();
    }

    private function getTreasuryAccounts(string $treasuryId): Collection
    {
        /** @phpstan-ignore-next-line */
        return Account::where('treasury_id', $treasuryId)->get();
    }

    private function getCurrentBalance(Collection $historicalData): float
    {
        // Get latest balance from transactions
        return $historicalData->sum(function ($transaction) {
            return $transaction->type === 'credit' ? $transaction->amount : -$transaction->amount;
        });
    }

    private function getCommittedOutflows(string $treasuryId, int $days): float
    {
        // Fetch scheduled payments, bills, etc.
        return (float) DB::table('scheduled_payments')
            ->where('treasury_id', $treasuryId)
            ->where('due_date', '<=', now()->addDays($days))
            ->where('status', 'pending')
            ->sum('amount');
    }

    private function getExpectedInflows(string $treasuryId, int $days): float
    {
        // Fetch expected receivables
        return (float) DB::table('expected_receivables')
            ->where('treasury_id', $treasuryId)
            ->where('expected_date', '<=', now()->addDays($days))
            ->where('status', 'pending')
            ->sum('amount');
    }

    private function determineLiquidityStatus(float $coverageRatio): string
    {
        return match (true) {
            $coverageRatio >= 2.0 => 'excellent',
            $coverageRatio >= 1.5 => 'good',
            $coverageRatio >= 1.0 => 'adequate',
            $coverageRatio >= 0.5 => 'concerning',
            default               => 'critical',
        };
    }

    private function calculateBufferDays(float $liquidity, string $treasuryId): int
    {
        // For now, estimate based on available liquidity (simplified calculation)
        // In production, this would analyze historical transaction patterns from the event store
        $estimatedDailyBurn = $liquidity * 0.05; // Assume 5% daily burn rate as baseline

        return $estimatedDailyBurn > 0 ? (int) ($liquidity / $estimatedDailyBurn) : 999;
    }

    private function generateBasicForecast(string $treasuryId, int $days): array
    {
        // Fallback for insufficient historical data
        return [
            'treasury_id'     => $treasuryId,
            'forecast_period' => $days,
            'generated_at'    => now()->toIso8601String(),
            'base_forecast'   => [],
            'scenarios'       => [],
            'risk_metrics'    => [
                'liquidity_coverage_ratio'  => 1.0,
                'net_stable_funding_ratio'  => 1.0,
                'stress_test_survival_days' => 30,
                'probability_of_shortage'   => 0.01,
                'value_at_risk_95'          => 0,
                'expected_shortfall'        => 0,
                'liquidity_buffer_adequacy' => 1.0,
            ],
            'alerts'           => [],
            'confidence_level' => 0.3,
            'recommendations'  => [
                [
                    'priority'        => 'high',
                    'category'        => 'data_collection',
                    'action'          => 'Accumulate more historical data for accurate forecasting',
                    'rationale'       => 'Insufficient data points for reliable forecast',
                    'expected_impact' => 'Enable advanced forecasting capabilities',
                ],
            ],
        ];
    }

    private function getDefaultScenarios(): array
    {
        return [
            'mild_stress' => [
                'description'        => 'Mild market stress with 20% reduction in inflows',
                'inflow_adjustment'  => 0.8,
                'outflow_adjustment' => 1.1,
            ],
            'moderate_stress' => [
                'description'        => 'Moderate stress with 40% reduction in inflows',
                'inflow_adjustment'  => 0.6,
                'outflow_adjustment' => 1.2,
            ],
            'severe_stress' => [
                'description'        => 'Severe stress with 60% reduction in inflows',
                'inflow_adjustment'  => 0.4,
                'outflow_adjustment' => 1.5,
            ],
        ];
    }

    private function applyScenario(array $forecast, array $scenario): array
    {
        $adjusted = [];
        $currentBalance = $forecast[0]['projected_balance'] ?? 0;

        foreach ($forecast as $day) {
            $adjustedInflow = $day['projected_inflow'] * ($scenario['inflow_adjustment'] ?? 1.0);
            $adjustedOutflow = $day['projected_outflow'] * ($scenario['outflow_adjustment'] ?? 1.0);
            $netFlow = $adjustedInflow - $adjustedOutflow;
            $currentBalance += $netFlow;

            $adjusted[] = [
                'date'              => $day['date'],
                'day_number'        => $day['day_number'],
                'projected_inflow'  => round($adjustedInflow, 2),
                'projected_outflow' => round($adjustedOutflow, 2),
                'net_flow'          => round($netFlow, 2),
                'projected_balance' => round($currentBalance, 2),
            ];
        }

        return $adjusted;
    }

    private function countDaysBelowThreshold(array $forecast, float $threshold = 0): int
    {
        return count(array_filter(
            $forecast,
            fn ($day) => $day['projected_balance'] < $threshold
        ));
    }

    private function estimateRecoveryTime(array $forecast): ?int
    {
        $belowThreshold = false;
        $recoveryDay = null;

        foreach ($forecast as $day) {
            if ($day['projected_balance'] < 0 && ! $belowThreshold) {
                $belowThreshold = true;
            } elseif ($day['projected_balance'] >= 0 && $belowThreshold) {
                $recoveryDay = $day['day_number'];
                break;
            }
        }

        return $recoveryDay;
    }

    private function getSeasonalFactor(Carbon $date, array $patterns): float
    {
        return $patterns['seasonal_patterns'][$date->month] ?? 1.0;
    }

    private function getDayOfWeekFactor(Carbon $date, array $patterns): float
    {
        return $patterns['day_of_week_patterns'][$date->dayOfWeek] ?? 1.0;
    }

    private function generateVolatility(float $baseVolatility): float
    {
        // Generate random volatility using normal distribution
        $random = (mt_rand() / mt_getrandmax() - 0.5) * 2;

        return $random * $baseVolatility;
    }

    private function calculateConfidenceLevel(Collection $historicalData, array $patterns): float
    {
        $dataPoints = $historicalData->count();
        $dataQuality = min($dataPoints / 100, 1.0); // Max confidence at 100+ data points

        $patternStrength = 0;
        if (! empty($patterns['seasonal_patterns'])) {
            $patternStrength += 0.25;
        }
        if (! empty($patterns['day_of_week_patterns'])) {
            $patternStrength += 0.25;
        }
        if (abs($patterns['trend'] ?? 0) < 0.5) {
            $patternStrength += 0.25;
        } // Stable trend
        if (($patterns['inflow_volatility'] ?? 1) < 0.3) {
            $patternStrength += 0.25;
        } // Low volatility

        return min($dataQuality * 0.6 + $patternStrength * 0.4, 1.0);
    }
}
