<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Workflows\Activities;

use App\Domain\Monitoring\Aggregates\MonitoringAggregate;
use App\Domain\Monitoring\Repositories\MonitoringAggregateRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AnalyzeThresholdsActivity
{
    private MonitoringAggregateRepository $repository;

    public function __construct(MonitoringAggregateRepository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(array $data): array
    {
        $metrics = $data['metrics'] ?? [];
        $thresholds = $data['thresholds'] ?? [];
        $healthStatus = $data['health_status'] ?? [];

        $alerts = [];

        // Analyze metric thresholds
        foreach ($metrics as $metric) {
            $alerts = array_merge($alerts, $this->analyzeMetric($metric, $thresholds));
        }

        // Analyze health status
        if ($healthStatus['status'] === 'unhealthy') {
            $alerts[] = $this->createHealthAlert($healthStatus);
        } elseif ($healthStatus['status'] === 'degraded') {
            $alerts[] = $this->createHealthWarning($healthStatus);
        }

        // Store alerts in event store
        if (! empty($alerts)) {
            $this->storeAlerts($alerts);
        }

        return $alerts;
    }

    private function analyzeMetric(array $metric, array $thresholds): array
    {
        $alerts = [];
        $name = $metric['name'];
        $value = $metric['value'];

        // Map metric names to threshold keys
        $thresholdMap = [
            'system_cpu_usage_percent'    => 'cpu_usage_percent',
            'system_memory_usage_percent' => 'memory_usage_percent',
            'app_error_rate_percent'      => 'error_rate_percent',
            'app_queue_jobs_pending'      => 'queue_size',
            'app_queue_jobs_failed_total' => 'failed_jobs',
            'http_request_duration_ms'    => 'response_time_ms',
        ];

        $thresholdKey = $thresholdMap[$name] ?? null;

        if ($thresholdKey && isset($thresholds[$thresholdKey])) {
            $threshold = $thresholds[$thresholdKey];

            if (isset($threshold['critical']) && $value >= $threshold['critical']) {
                $alerts[] = [
                    'name'      => "{$name}_critical",
                    'severity'  => 'critical',
                    'message'   => "Critical: {$name} is {$value}, threshold is {$threshold['critical']}",
                    'metric'    => $name,
                    'value'     => $value,
                    'threshold' => $threshold['critical'],
                    'timestamp' => now()->toIso8601String(),
                ];
            } elseif (isset($threshold['warning']) && $value >= $threshold['warning']) {
                $alerts[] = [
                    'name'      => "{$name}_warning",
                    'severity'  => 'warning',
                    'message'   => "Warning: {$name} is {$value}, threshold is {$threshold['warning']}",
                    'metric'    => $name,
                    'value'     => $value,
                    'threshold' => $threshold['warning'],
                    'timestamp' => now()->toIso8601String(),
                ];
            }
        }

        return $alerts;
    }

    private function createHealthAlert(array $healthStatus): array
    {
        $unhealthyComponents = [];

        foreach ($healthStatus['checks'] ?? [] as $component => $check) {
            if ($check['status'] === 'unhealthy') {
                $unhealthyComponents[] = $component;
            }
        }

        return [
            'name'          => 'system_health_critical',
            'severity'      => 'critical',
            'message'       => 'Critical: System health check failed. Unhealthy components: ' . implode(', ', $unhealthyComponents),
            'components'    => $unhealthyComponents,
            'health_status' => $healthStatus,
            'timestamp'     => now()->toIso8601String(),
        ];
    }

    private function createHealthWarning(array $healthStatus): array
    {
        $degradedComponents = [];

        foreach ($healthStatus['checks'] ?? [] as $component => $check) {
            if ($check['status'] === 'degraded') {
                $degradedComponents[] = $component;
            }
        }

        return [
            'name'          => 'system_health_degraded',
            'severity'      => 'warning',
            'message'       => 'Warning: System health degraded. Affected components: ' . implode(', ', $degradedComponents),
            'components'    => $degradedComponents,
            'health_status' => $healthStatus,
            'timestamp'     => now()->toIso8601String(),
        ];
    }

    private function storeAlerts(array $alerts): void
    {
        $aggregate = $this->getOrCreateAggregate();

        foreach ($alerts as $alert) {
            $aggregate->triggerAlert(
                $alert['name'],
                $alert['severity'],
                $alert['message'],
                $alert
            );

            // Check thresholds if metric-based alert
            if (isset($alert['metric']) && isset($alert['threshold'])) {
                $aggregate->checkThreshold(
                    $alert['metric'],
                    $alert['value'],
                    $alert['threshold'],
                    '>='
                );
            }
        }

        $this->repository->store($aggregate);
    }

    private function getOrCreateAggregate(): MonitoringAggregate
    {
        $sessionId = Cache::get('monitoring:session:id', Str::uuid()->toString());

        $aggregate = $this->repository->findBySessionId($sessionId);

        if (! $aggregate) {
            $aggregate = MonitoringAggregate::fake(Str::uuid()->toString());
            $aggregate->startSession($sessionId, [
                'type'       => 'threshold_analysis',
                'started_at' => now(),
            ]);
        }

        return $aggregate;
    }
}
