<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Workflows;

use App\Domain\Monitoring\Workflows\Activities\AnalyzeThresholdsActivity;
use App\Domain\Monitoring\Workflows\Activities\CheckHealthActivity;
use App\Domain\Monitoring\Workflows\Activities\CollectMetricsActivity;
use App\Domain\Monitoring\Workflows\Activities\ExportMetricsActivity;
use App\Domain\Monitoring\Workflows\Activities\SendAlertsActivity;
use Carbon\CarbonInterval;
use Workflow\Activity\ActivityOptions;
use Workflow\Workflow;

class MonitoringWorkflow extends Workflow
{
    private array $metrics = [];

    private array $healthStatus = [];

    private array $alerts = [];

    private bool $shouldStop = false;

    /**
     * Execute the monitoring workflow.
     */
    public function execute(array $config = []): \Generator
    {
        $interval = $config['interval'] ?? 60; // Default 60 seconds
        $maxIterations = $config['max_iterations'] ?? null; // Run indefinitely by default
        $thresholds = $config['thresholds'] ?? $this->getDefaultThresholds();

        $iteration = 0;

        while (! $this->shouldStop && ($maxIterations === null || $iteration < $maxIterations)) {
            try {
                // Start distributed trace for this monitoring cycle
                yield Workflow::await(fn () => true);

                // Collect metrics
                $this->metrics = yield Workflow::executeActivity(
                    CollectMetricsActivity::class,
                    ActivityOptions::new()
                        ->withStartToCloseTimeout(CarbonInterval::seconds(30))
                        ->withRetryOptions(3, CarbonInterval::seconds(2))
                );

                // Perform health checks
                $this->healthStatus = yield Workflow::executeActivity(
                    CheckHealthActivity::class,
                    ActivityOptions::new()
                        ->withStartToCloseTimeout(CarbonInterval::seconds(30))
                        ->withRetryOptions(3, CarbonInterval::seconds(2))
                );

                // Analyze thresholds and generate alerts
                $this->alerts = yield Workflow::executeActivity(
                    AnalyzeThresholdsActivity::class,
                    [
                        'metrics'       => $this->metrics,
                        'thresholds'    => $thresholds,
                        'health_status' => $this->healthStatus,
                    ],
                    ActivityOptions::new()
                        ->withStartToCloseTimeout(CarbonInterval::seconds(10))
                );

                // Send alerts if any
                if (! empty($this->alerts)) {
                    yield Workflow::executeActivity(
                        SendAlertsActivity::class,
                        ['alerts' => $this->alerts],
                        ActivityOptions::new()
                            ->withStartToCloseTimeout(CarbonInterval::seconds(30))
                            ->withRetryOptions(3, CarbonInterval::seconds(5))
                    );
                }

                // Export metrics for Prometheus
                yield Workflow::executeActivity(
                    ExportMetricsActivity::class,
                    [
                        'metrics'       => $this->metrics,
                        'health_status' => $this->healthStatus,
                    ],
                    ActivityOptions::new()
                        ->withStartToCloseTimeout(CarbonInterval::seconds(10))
                );

                // Wait for next iteration
                yield Workflow::timer(CarbonInterval::seconds($interval));

                $iteration++;

            } catch (\Exception $e) {
                // Log error but continue monitoring
                yield Workflow::sideEffect(function () use ($e) {
                    \Log::error('Monitoring workflow error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                });

                // Wait before retrying
                yield Workflow::timer(CarbonInterval::seconds(10));
            }
        }

        return [
            'status'              => 'completed',
            'iterations'          => $iteration,
            'final_metrics'       => $this->metrics,
            'final_health_status' => $this->healthStatus,
            'total_alerts'        => count($this->alerts),
        ];
    }

    /**
     * Signal to stop the monitoring workflow.
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }

    /**
     * Update monitoring thresholds.
     */
    public function updateThresholds(array $thresholds): \Generator
    {
        yield Workflow::sideEffect(function () use ($thresholds) {
            \Cache::put('monitoring:thresholds', $thresholds, now()->addDays(30));
        });
    }

    /**
     * Get current monitoring status.
     */
    public function getStatus(): array
    {
        return [
            'is_running'      => ! $this->shouldStop,
            'current_metrics' => $this->metrics,
            'current_health'  => $this->healthStatus,
            'pending_alerts'  => $this->alerts,
        ];
    }

    /**
     * Get default monitoring thresholds.
     */
    private function getDefaultThresholds(): array
    {
        return [
            'cpu_usage_percent' => [
                'warning'  => 70,
                'critical' => 90,
            ],
            'memory_usage_percent' => [
                'warning'  => 80,
                'critical' => 95,
            ],
            'disk_usage_percent' => [
                'warning'  => 80,
                'critical' => 90,
            ],
            'response_time_ms' => [
                'warning'  => 1000,
                'critical' => 5000,
            ],
            'error_rate_percent' => [
                'warning'  => 1,
                'critical' => 5,
            ],
            'queue_size' => [
                'warning'  => 100,
                'critical' => 1000,
            ],
            'failed_jobs' => [
                'warning'  => 10,
                'critical' => 100,
            ],
        ];
    }
}
