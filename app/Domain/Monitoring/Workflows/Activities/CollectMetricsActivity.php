<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Workflows\Activities;

use App\Domain\Monitoring\Services\MetricsCollector;

class CollectMetricsActivity
{
    private MetricsCollector $collector;

    public function __construct(MetricsCollector $collector)
    {
        $this->collector = $collector;
    }

    public function execute(): array
    {
        // Collect application metrics
        $this->collector->recordApplicationMetrics();

        // Collect business metrics
        $this->collector->recordBusinessMetrics();

        // Collect custom metrics
        $this->collectCustomMetrics();

        // Return all collected metrics
        return $this->collector->getAllMetrics();
    }

    private function collectCustomMetrics(): void
    {
        // CPU usage (simulated - in production, use actual system metrics)
        $cpuUsage = sys_getloadavg()[0] * 100 / cpu_count();
        $this->collector->setGauge('system_cpu_usage_percent', min($cpuUsage, 100));

        // Memory usage
        $memoryUsage = (memory_get_usage(true) / memory_limit()) * 100;
        $this->collector->setGauge('system_memory_usage_percent', $memoryUsage);

        // Response time tracking
        if (! app()->runningInConsole()) {
            $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->collector->recordHistogram(
                'http_request_duration_ms',
                $responseTime,
                [50, 100, 200, 500, 1000, 2000, 5000],
                [
                    'method' => request()->method(),
                    'route'  => request()->route()?->getName() ?? 'unknown',
                ]
            );
        }

        // Error rate
        $totalRequests = \Cache::get('metrics:requests:total', 1);
        $errorRequests = \Cache::get('metrics:requests:errors', 0);
        $errorRate = ($errorRequests / $totalRequests) * 100;
        $this->collector->setGauge('app_error_rate_percent', $errorRate);

        // Active sessions
        $activeSessions = \DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(30)->timestamp)
            ->count();
        $this->collector->setGauge('app_sessions_active', $activeSessions);

        // Event sourcing metrics
        $this->collectEventSourcingMetrics();

        // Workflow metrics
        $this->collectWorkflowMetrics();
    }

    private function collectEventSourcingMetrics(): void
    {
        $tables = [
            'exchange_events',
            'lending_events',
            'stablecoin_events',
            'wallet_events',
            'cgo_events',
            'treasury_events',
            'monitoring_events',
        ];

        foreach ($tables as $table) {
            if (\Schema::hasTable($table)) {
                $count = \DB::table($table)->count();
                $domain = str_replace('_events', '', $table);
                $this->collector->setGauge(
                    'app_events_total',
                    $count,
                    ['domain' => $domain],
                    null,
                    "Total events in {$domain} domain"
                );

                // Events per day
                $dailyEvents = \DB::table($table)
                    ->whereDate('created_at', today())
                    ->count();
                $this->collector->setGauge(
                    'app_events_daily',
                    $dailyEvents,
                    ['domain' => $domain],
                    null,
                    "Daily events in {$domain} domain"
                );
            }
        }
    }

    private function collectWorkflowMetrics(): void
    {
        // Workflow execution metrics
        $workflowsStarted = \Cache::get('metrics:workflows:started', 0);
        $workflowsCompleted = \Cache::get('metrics:workflows:completed', 0);
        $workflowsFailed = \Cache::get('metrics:workflows:failed', 0);

        $this->collector->setGauge('workflow_executions_started', $workflowsStarted);
        $this->collector->setGauge('workflow_executions_completed', $workflowsCompleted);
        $this->collector->setGauge('workflow_executions_failed', $workflowsFailed);

        if ($workflowsStarted > 0) {
            $successRate = ($workflowsCompleted / $workflowsStarted) * 100;
            $this->collector->setGauge('workflow_success_rate_percent', $successRate);
        }
    }

    private function memory_limit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $limit;
        $unit = strtolower(substr($limit, -1));

        switch ($unit) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    private function cpu_count(): int
    {
        if (function_exists('swoole_cpu_num')) {
            return swoole_cpu_num();
        }

        $cpuinfo = file_get_contents('/proc/cpuinfo');
        preg_match_all('/^processor/m', $cpuinfo, $matches);

        return count($matches[0]) ?: 1;
    }
}
