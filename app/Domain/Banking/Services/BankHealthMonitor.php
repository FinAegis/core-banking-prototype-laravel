<?php

declare(strict_types=1);

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Contracts\IBankConnector;
use App\Domain\Banking\Events\BankHealthChanged;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BankHealthMonitor
{
    private array $banks = [];

    private array $healthChecks = [];

    /**
     * Register a bank for health monitoring.
     */
    public function registerBank(string $bankCode, IBankConnector $connector): void
    {
        $this->banks[$bankCode] = $connector;
    }

    /**
     * Check health of a specific bank.
     */
    public function checkHealth(string $bankCode): array
    {
        if (! isset($this->banks[$bankCode])) {
            return [
                'status'    => 'unknown',
                'message'   => 'Bank not registered',
                'timestamp' => now()->toIso8601String(),
            ];
        }

        $cacheKey = "bank_health:{$bankCode}";

        return Cache::remember(
            $cacheKey,
            60,
            function () use ($bankCode) {
                try {
                    $connector = $this->banks[$bankCode];
                    $startTime = microtime(true);

                    $isAvailable = $connector->isAvailable();
                    $responseTime = (microtime(true) - $startTime) * 1000; // Convert to ms

                    $health = [
                        'status'           => $isAvailable ? 'healthy' : 'unhealthy',
                        'available'        => $isAvailable,
                        'response_time_ms' => round($responseTime, 2),
                        'last_check'       => now()->toIso8601String(),
                        'capabilities'     => $connector->getCapabilities()->toArray(),
                    ];

                    // Additional health checks
                    if ($isAvailable) {
                        $health['supported_currencies'] = $connector->getSupportedCurrencies();
                    }

                    $this->recordHealthCheck($bankCode, $health);

                    return $health;
                } catch (Exception $e) {
                    Log::error(
                        'Bank health check failed',
                        [
                            'bank_code' => $bankCode,
                            'error'     => $e->getMessage(),
                        ]
                    );

                    $health = [
                        'status'     => 'error',
                        'available'  => false,
                        'error'      => $e->getMessage(),
                        'last_check' => now()->toIso8601String(),
                    ];

                    $this->recordHealthCheck($bankCode, $health);

                    return $health;
                }
            }
        );
    }

    /**
     * Check health of all registered banks.
     */
    public function checkAllBanks(): array
    {
        $results = [];

        foreach (array_keys($this->banks) as $bankCode) {
            $results[$bankCode] = $this->checkHealth($bankCode);
        }

        return $results;
    }

    /**
     * Get health history for a bank.
     */
    public function getHealthHistory(string $bankCode, int $hours = 24): array
    {
        $cacheKey = "bank_health_history:{$bankCode}";
        $history = Cache::get($cacheKey, []);

        // Filter by time range
        $cutoff = now()->subHours($hours);

        return array_filter(
            $history,
            function ($check) use ($cutoff) {
                return isset($check['timestamp']) &&
                   \Carbon\Carbon::parse($check['timestamp'])->isAfter($cutoff);
            }
        );
    }

    /**
     * Get aggregated health metrics.
     */
    public function getHealthMetrics(): array
    {
        $allHealth = $this->checkAllBanks();

        $metrics = [
            'total_banks'           => count($this->banks),
            'healthy_banks'         => 0,
            'unhealthy_banks'       => 0,
            'average_response_time' => 0,
            'checks_performed'      => count($this->healthChecks),
            'last_check'            => now()->toIso8601String(),
        ];

        $totalResponseTime = 0;
        $responseCount = 0;

        foreach ($allHealth as $bankCode => $health) {
            if ($health['status'] === 'healthy') {
                $metrics['healthy_banks']++;
            } else {
                $metrics['unhealthy_banks']++;
            }

            if (isset($health['response_time_ms'])) {
                $totalResponseTime += $health['response_time_ms'];
                $responseCount++;
            }
        }

        if ($responseCount > 0) {
            $metrics['average_response_time'] = round($totalResponseTime / $responseCount, 2);
        }

        return $metrics;
    }

    /**
     * Record a health check result.
     */
    private function recordHealthCheck(string $bankCode, array $health): void
    {
        // Store in memory for current process
        $this->healthChecks[$bankCode][] = array_merge(
            $health,
            [
                'timestamp' => now()->toIso8601String(),
            ]
        );

        // Keep only last 100 checks per bank
        if (count($this->healthChecks[$bankCode]) > 100) {
            array_shift($this->healthChecks[$bankCode]);
        }

        // Store in cache for persistence
        $cacheKey = "bank_health_history:{$bankCode}";
        $history = Cache::get($cacheKey, []);

        array_push(
            $history,
            array_merge(
                $health,
                [
                    'timestamp' => now()->toIso8601String(),
                ]
            )
        );

        // Keep only last 1000 checks
        if (count($history) > 1000) {
            array_shift($history);
        }

        Cache::put($cacheKey, $history, now()->addDays(7));

        // Emit event if status changed
        $this->checkStatusChange($bankCode, $health);
    }

    /**
     * Check if bank status has changed.
     */
    private function checkStatusChange(string $bankCode, array $currentHealth): void
    {
        $previousKey = "bank_previous_status:{$bankCode}";
        $previousStatus = Cache::get($previousKey);

        if ($previousStatus !== $currentHealth['status']) {
            event(
                new BankHealthChanged(
                    $bankCode,
                    $previousStatus,
                    $currentHealth['status'],
                    $currentHealth
                )
            );

            Cache::put($previousKey, $currentHealth['status'], now()->addDays(1));
        }
    }

    /**
     * Get banks by health status.
     */
    public function getBanksByStatus(string $status): array
    {
        $allHealth = $this->checkAllBanks();

        return array_filter(
            $allHealth,
            function ($health) use ($status) {
                return $health['status'] === $status;
            }
        );
    }

    /**
     * Calculate uptime percentage.
     */
    public function getUptimePercentage(string $bankCode, int $hours = 24): float
    {
        $history = $this->getHealthHistory($bankCode, $hours);

        if (empty($history)) {
            return 0.0;
        }

        $healthyChecks = array_filter(
            $history,
            function ($check) {
                return $check['status'] === 'healthy';
            }
        );

        return round((count($healthyChecks) / count($history)) * 100, 2);
    }
}
