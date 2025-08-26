<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Workflows;

use App\Domain\Treasury\Activities\ApplyLiquidityMitigationActivity;
use App\Domain\Treasury\Activities\GenerateLiquidityForecastActivity;
use App\Domain\Treasury\Activities\SendLiquidityAlertActivity;
use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowInterface;

/**
 * Workflow for automated liquidity forecasting and alerting.
 */
#[WorkflowInterface]
class LiquidityForecastingWorkflow
{
    private array $forecastResults = [];

    private array $alerts = [];

    private bool $isRunning = true;

    private ?string $treasuryId = null;

    private int $forecastDays = 30;

    /** @phpstan-ignore-next-line */
    public function execute(string $treasuryId, array $config = []): \Generator
    {
        $this->treasuryId = $treasuryId;
        $this->forecastDays = $config['forecast_days'] ?? 30;
        $updateInterval = $config['update_interval_hours'] ?? 6;

        // Initial forecast
        yield $this->generateForecast();

        // Set up periodic updates
        while ($this->isRunning) {
            // Wait for update interval
            yield Workflow::timer($updateInterval * 3600);

            /** @phpstan-ignore-next-line */
            if (! $this->isRunning) {
                break;
            }

            // Generate updated forecast
            yield $this->generateForecast();

            // Check for critical alerts
            if ($this->hasCriticalAlerts()) {
                yield $this->triggerAlertNotifications();
            }

            // Apply automatic mitigation if configured
            if ($config['auto_mitigation'] ?? false) {
                yield $this->applyAutomaticMitigation();
            }
        }

        return [
            'status'         => 'completed',
            'treasury_id'    => $this->treasuryId,
            'final_forecast' => $this->forecastResults,
            'total_alerts'   => count($this->alerts),
            'completed_at'   => now()->toIso8601String(),
        ];
    }

    private function generateForecast(): \Generator
    {
        $activity = yield ActivityStub::make(
            GenerateLiquidityForecastActivity::class,
            [
                'startToCloseTimeout' => 60,
                'retryPolicy'         => [
                    'initialInterval' => 1,
                    'maximumAttempts' => 3,
                ],
            ]
        );

        $result = yield $activity->execute(
            $this->treasuryId,
            $this->forecastDays
        );

        $this->forecastResults = $result;
        $this->alerts = $result['alerts'] ?? [];

        return $result;
    }

    private function triggerAlertNotifications(): \Generator
    {
        $activity = yield ActivityStub::make(
            SendLiquidityAlertActivity::class,
            [
                'startToCloseTimeout' => 30,
            ]
        );

        return yield $activity->execute(
            $this->treasuryId,
            array_filter($this->alerts, fn ($alert) => $alert['level'] === 'critical')
        );
    }

    private function applyAutomaticMitigation(): \Generator
    {
        if (empty($this->alerts)) {
            return null;
        }

        $activity = yield ActivityStub::make(
            ApplyLiquidityMitigationActivity::class,
            [
                'startToCloseTimeout' => 120,
            ]
        );

        return yield $activity->execute(
            $this->treasuryId,
            $this->forecastResults,
            $this->alerts
        );
    }

    /** @phpstan-ignore-next-line */
    public function updateForecastDays(int $days): void
    {
        $this->forecastDays = max(1, min(365, $days));
    }

    /** @phpstan-ignore-next-line */
    public function stop(): void
    {
        $this->isRunning = false;
    }

    private function hasCriticalAlerts(): bool
    {
        return ! empty(array_filter(
            $this->alerts,
            fn ($alert) => $alert['level'] === 'critical'
        ));
    }
}
