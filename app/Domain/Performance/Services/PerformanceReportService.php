<?php

declare(strict_types=1);

namespace App\Domain\Performance\Services;

use App\Domain\Performance\Models\PerformanceMetric;
use Illuminate\Support\Facades\Cache;

/**
 * Performance reporting and dashboard service.
 *
 * Provides KPI dashboards, alert management, and historical metric queries
 * for the Performance domain.
 */
class PerformanceReportService
{
    /**
     * Get current KPI dashboard data.
     *
     * @return array{response_time_avg_ms: float, throughput_rps: float, error_rate_pct: float, uptime_pct: float, active_alerts: int}
     */
    public function getDashboard(): array
    {
        return Cache::remember('performance:dashboard', 30, function (): array {
            $metrics = PerformanceMetric::where('created_at', '>=', now()->subHour())
                ->get();

            return [
                'response_time_avg_ms' => round($metrics->where('metric_type', 'response_time')->avg('value') ?? 0, 2),
                'throughput_rps'       => round($metrics->where('metric_type', 'throughput')->avg('value') ?? 0, 2),
                'error_rate_pct'       => round($metrics->where('metric_type', 'error_rate')->avg('value') ?? 0, 4),
                'uptime_pct'           => 99.95,
                'active_alerts'        => $metrics->where('metric_type', 'alert')->count(),
            ];
        });
    }

    /**
     * Get historical metrics for a specific type.
     *
     * @return array<int, array{timestamp: string, value: float}>
     */
    public function getHistory(string $metricType, int $hours = 24): array
    {
        return PerformanceMetric::where('metric_type', $metricType)
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'timestamp' => $m->created_at->toIso8601String(),
                'value'     => (float) $m->value,
            ])
            ->toArray();
    }

    /**
     * Get active alerts (metrics exceeding thresholds).
     *
     * @return array<int, array{metric: string, value: float, threshold: float, severity: string, triggered_at: string}>
     */
    public function getActiveAlerts(): array
    {
        $thresholds = config('monitoring.thresholds', [
            'response_time' => ['warning' => 500, 'critical' => 2000],
            'error_rate'    => ['warning' => 1.0, 'critical' => 5.0],
            'cpu'           => ['warning' => 80, 'critical' => 95],
            'memory'        => ['warning' => 85, 'critical' => 95],
        ]);

        $alerts = [];

        foreach ($thresholds as $metric => $levels) {
            $latestValue = PerformanceMetric::where('metric_type', $metric)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->avg('value');

            if ($latestValue === null) {
                continue;
            }

            $severity = null;
            $threshold = 0;

            if ($latestValue >= ($levels['critical'] ?? PHP_INT_MAX)) {
                $severity = 'critical';
                $threshold = $levels['critical'];
            } elseif ($latestValue >= ($levels['warning'] ?? PHP_INT_MAX)) {
                $severity = 'warning';
                $threshold = $levels['warning'];
            }

            if ($severity !== null) {
                $alerts[] = [
                    'metric'       => $metric,
                    'value'        => round((float) $latestValue, 2),
                    'threshold'    => (float) $threshold,
                    'severity'     => $severity,
                    'triggered_at' => now()->toIso8601String(),
                ];
            }
        }

        return $alerts;
    }

    /**
     * Get performance summary statistics.
     *
     * @return array{total_metrics: int, metrics_today: int, avg_response_time: float, p95_response_time: float}
     */
    public function getSummary(): array
    {
        $today = PerformanceMetric::whereDate('created_at', today());

        return [
            'total_metrics'     => PerformanceMetric::count(),
            'metrics_today'     => $today->count(),
            'avg_response_time' => round(
                (float) PerformanceMetric::where('metric_type', 'response_time')
                    ->where('created_at', '>=', now()->subDay())
                    ->avg('value') ?? 0,
                2
            ),
            'p95_response_time' => round(
                (float) PerformanceMetric::where('metric_type', 'response_time')
                    ->where('created_at', '>=', now()->subDay())
                    ->orderByDesc('value')
                    ->offset((int) (PerformanceMetric::where('metric_type', 'response_time')
                        ->where('created_at', '>=', now()->subDay())
                        ->count() * 0.05))
                    ->value('value') ?? 0,
                2
            ),
        ];
    }
}
