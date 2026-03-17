<?php

declare(strict_types=1);

namespace App\Domain\Performance\Services;

use App\Domain\Performance\Models\PerformanceMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Performance reporting and dashboard service.
 *
 * Provides KPI dashboards, alert management, and historical metric queries.
 * All queries use DB-level aggregation to avoid loading full datasets into memory.
 */
class PerformanceReportService
{
    /**
     * Get current KPI dashboard data (cached 60s).
     *
     * @return array{response_time_avg_ms: float, throughput_rps: float, error_rate_pct: float, active_alerts: int}
     */
    public function getDashboard(): array
    {
        return Cache::remember('performance:dashboard', 60, function (): array {
            $since = now()->subHour();

            $averages = PerformanceMetric::where('created_at', '>=', $since)
                ->selectRaw("
                    AVG(CASE WHEN type = 'response_time' THEN value END) as avg_response_time,
                    AVG(CASE WHEN type = 'throughput' THEN value END) as avg_throughput,
                    AVG(CASE WHEN type = 'error_rate' THEN value END) as avg_error_rate,
                    SUM(CASE WHEN type = 'alert' THEN 1 ELSE 0 END) as alert_count
                ")
                ->first();

            return [
                'response_time_avg_ms' => round((float) ($averages->avg_response_time ?? 0), 2),
                'throughput_rps'       => round((float) ($averages->avg_throughput ?? 0), 2),
                'error_rate_pct'       => round((float) ($averages->avg_error_rate ?? 0), 4),
                'active_alerts'        => (int) ($averages->alert_count ?? 0),
            ];
        });
    }

    /**
     * Get historical metrics for a specific type (limited to 1000 rows).
     *
     * @return array<int, array{timestamp: string, value: float}>
     */
    public function getHistory(string $metricType, int $hours = 24, int $limit = 1000): array
    {
        return PerformanceMetric::byType($metricType)
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($m) => [
                'timestamp' => $m->created_at->toIso8601String(),
                'value'     => (float) $m->value,
            ])
            ->toArray();
    }

    /**
     * Get active alerts — single query for all thresholds.
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

        $types = array_keys($thresholds);
        $since = now()->subMinutes(5);

        // Single grouped query instead of N+1
        $averages = PerformanceMetric::whereIn('type', $types)
            ->where('created_at', '>=', $since)
            ->selectRaw('type, AVG(value) as avg_value')
            ->groupBy('type')
            ->pluck('avg_value', 'type');

        $alerts = [];

        foreach ($thresholds as $metric => $levels) {
            $value = (float) ($averages[$metric] ?? 0);
            if ($value === 0.0) {
                continue;
            }

            $severity = null;
            $threshold = 0;

            if ($value >= ($levels['critical'] ?? PHP_INT_MAX)) {
                $severity = 'critical';
                $threshold = $levels['critical'];
            } elseif ($value >= ($levels['warning'] ?? PHP_INT_MAX)) {
                $severity = 'warning';
                $threshold = $levels['warning'];
            }

            if ($severity !== null) {
                $alerts[] = [
                    'metric'       => $metric,
                    'value'        => round($value, 2),
                    'threshold'    => (float) $threshold,
                    'severity'     => $severity,
                    'triggered_at' => now()->toIso8601String(),
                ];
            }
        }

        return $alerts;
    }

    /**
     * Get performance summary (cached 60s).
     *
     * @return array{total_metrics: int, metrics_today: int, avg_response_time: float}
     */
    public function getSummary(): array
    {
        return Cache::remember('performance:summary', 60, function (): array {
            $summary = PerformanceMetric::selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                AVG(CASE WHEN type = 'response_time' AND created_at >= ? THEN value END) as avg_rt
            ", [now()->subDay()])
                ->first();

            return [
                'total_metrics'     => (int) ($summary->total ?? 0),
                'metrics_today'     => (int) ($summary->today ?? 0),
                'avg_response_time' => round((float) ($summary->avg_rt ?? 0), 2),
            ];
        });
    }
}
