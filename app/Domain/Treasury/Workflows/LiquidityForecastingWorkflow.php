<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Workflows;

use App\Domain\Treasury\Services\LiquidityForecastingService;
use App\Domain\Treasury\ValueObjects\LiquidityMetrics;
use Workflow\Activity;
use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowInterface;
use Workflow\WorkflowMethod;

/**
 * Workflow for automated liquidity forecasting and alerting
 */
#[WorkflowInterface]
class LiquidityForecastingWorkflow
{
    private array $forecastResults = [];
    private array $alerts = [];
    private bool $isRunning = true;
    private ?string $treasuryId = null;
    private int $forecastDays = 30;
    
    #[WorkflowMethod]
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
            
            if (!$this->isRunning) {
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
            'status' => 'completed',
            'treasury_id' => $this->treasuryId,
            'final_forecast' => $this->forecastResults,
            'total_alerts' => count($this->alerts),
            'completed_at' => now()->toIso8601String(),
        ];
    }
    
    #[Activity]
    private function generateForecast(): \Generator
    {
        $activity = yield ActivityStub::make(
            GenerateLiquidityForecastActivity::class,
            [
                'startToCloseTimeout' => 60,
                'retryPolicy' => [
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
    
    #[Activity]
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
            array_filter($this->alerts, fn($alert) => $alert['level'] === 'critical')
        );
    }
    
    #[Activity]
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
    
    #[SignalMethod]
    public function updateForecastDays(int $days): void
    {
        $this->forecastDays = max(1, min(365, $days));
    }
    
    #[SignalMethod]
    public function stop(): void
    {
        $this->isRunning = false;
    }
    
    private function hasCriticalAlerts(): bool
    {
        return !empty(array_filter(
            $this->alerts,
            fn($alert) => $alert['level'] === 'critical'
        ));
    }
}

/**
 * Activity for generating liquidity forecast
 */
class GenerateLiquidityForecastActivity
{
    public function __construct(
        private readonly LiquidityForecastingService $forecastingService
    ) {}
    
    #[Activity]
    public function execute(string $treasuryId, int $forecastDays): array
    {
        return $this->forecastingService->generateForecast(
            $treasuryId,
            $forecastDays
        );
    }
}

/**
 * Activity for sending liquidity alerts
 */
class SendLiquidityAlertActivity
{
    #[Activity]
    public function execute(string $treasuryId, array $alerts): array
    {
        $notifications = [];
        
        foreach ($alerts as $alert) {
            // In production, would send actual notifications
            // via email, SMS, Slack, etc.
            $notifications[] = [
                'type' => 'liquidity_alert',
                'treasury_id' => $treasuryId,
                'alert' => $alert,
                'sent_at' => now()->toIso8601String(),
                'channels' => ['email', 'dashboard'],
            ];
        }
        
        return [
            'notifications_sent' => count($notifications),
            'details' => $notifications,
        ];
    }
}

/**
 * Activity for applying automatic liquidity mitigation
 */
class ApplyLiquidityMitigationActivity
{
    #[Activity]
    public function execute(
        string $treasuryId,
        array $forecast,
        array $alerts
    ): array {
        $mitigationActions = [];
        
        foreach ($alerts as $alert) {
            switch ($alert['type']) {
                case 'negative_balance':
                    $mitigationActions[] = [
                        'action' => 'accelerate_collections',
                        'description' => 'Initiate accelerated receivables collection',
                        'expected_impact' => 'Improve cash position by 10-15%',
                    ];
                    break;
                    
                case 'lcr_breach':
                    $mitigationActions[] = [
                        'action' => 'liquidate_investments',
                        'description' => 'Liquidate non-essential short-term investments',
                        'expected_impact' => 'Increase HQLA by 20-30%',
                    ];
                    break;
                    
                case 'stress_resilience':
                    $mitigationActions[] = [
                        'action' => 'secure_credit_facility',
                        'description' => 'Activate standby credit facilities',
                        'expected_impact' => 'Extend survival period by 30-45 days',
                    ];
                    break;
            }
        }
        
        return [
            'treasury_id' => $treasuryId,
            'actions_taken' => count($mitigationActions),
            'mitigation_details' => $mitigationActions,
            'executed_at' => now()->toIso8601String(),
        ];
    }
}
