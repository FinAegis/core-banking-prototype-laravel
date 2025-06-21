<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Custodian\Events\CustodianHealthChanged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CustodianHealthMonitor
{
    /**
     * Health status constants
     */
    private const STATUS_HEALTHY = 'healthy';
    private const STATUS_DEGRADED = 'degraded';
    private const STATUS_UNHEALTHY = 'unhealthy';
    
    /**
     * Thresholds for health status
     */
    private const DEGRADED_FAILURE_RATE = 0.3; // 30% failure rate
    private const UNHEALTHY_FAILURE_RATE = 0.7; // 70% failure rate
    private const CIRCUIT_OPEN_UNHEALTHY = true; // Circuit open = unhealthy
    
    public function __construct(
        private readonly CustodianRegistry $registry,
        private readonly CircuitBreakerService $circuitBreaker
    ) {}
    
    /**
     * Get health status for all custodians
     */
    public function getAllCustodiansHealth(): array
    {
        $health = [];
        
        foreach ($this->registry->getAllConnectorNames() as $custodian) {
            $health[$custodian] = $this->getCustodianHealth($custodian);
        }
        
        return $health;
    }
    
    /**
     * Get health status for a specific custodian
     */
    public function getCustodianHealth(string $custodian): array
    {
        try {
            $connector = $this->registry->getConnector($custodian);
            $isAvailable = $connector->isAvailable();
            
            // Get circuit breaker metrics for various operations
            $operations = ['getBalance', 'initiateTransfer', 'getTransactionStatus'];
            $circuitMetrics = [];
            $overallFailureRate = 0;
            $anyCircuitOpen = false;
            
            foreach ($operations as $operation) {
                $metrics = $connector->getCircuitBreakerMetrics($operation);
                $circuitMetrics[$operation] = $metrics;
                
                if ($metrics['state'] === 'open') {
                    $anyCircuitOpen = true;
                }
                
                $overallFailureRate = max($overallFailureRate, $metrics['failure_rate'] / 100);
            }
            
            // Determine health status
            $status = $this->determineHealthStatus($isAvailable, $anyCircuitOpen, $overallFailureRate);
            
            // Check if status changed
            $this->checkAndNotifyHealthChange($custodian, $status);
            
            return [
                'custodian' => $custodian,
                'status' => $status,
                'available' => $isAvailable,
                'circuit_breaker_metrics' => $circuitMetrics,
                'overall_failure_rate' => round($overallFailureRate * 100, 2),
                'last_check' => now()->toIso8601String(),
                'recommendations' => $this->getRecommendations($status, $overallFailureRate),
            ];
            
        } catch (\Exception $e) {
            Log::error("Failed to get health for custodian: {$custodian}", [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'custodian' => $custodian,
                'status' => self::STATUS_UNHEALTHY,
                'available' => false,
                'error' => $e->getMessage(),
                'last_check' => now()->toIso8601String(),
            ];
        }
    }
    
    /**
     * Determine health status based on metrics
     */
    private function determineHealthStatus(bool $isAvailable, bool $anyCircuitOpen, float $failureRate): string
    {
        if (!$isAvailable || ($anyCircuitOpen && self::CIRCUIT_OPEN_UNHEALTHY)) {
            return self::STATUS_UNHEALTHY;
        }
        
        if ($failureRate >= self::UNHEALTHY_FAILURE_RATE) {
            return self::STATUS_UNHEALTHY;
        }
        
        if ($failureRate >= self::DEGRADED_FAILURE_RATE) {
            return self::STATUS_DEGRADED;
        }
        
        return self::STATUS_HEALTHY;
    }
    
    /**
     * Get recommendations based on health status
     */
    private function getRecommendations(string $status, float $failureRate): array
    {
        $recommendations = [];
        
        switch ($status) {
            case self::STATUS_UNHEALTHY:
                $recommendations[] = 'Consider switching to alternative custodian';
                $recommendations[] = 'Queue non-critical transfers for retry';
                $recommendations[] = 'Alert operations team immediately';
                break;
                
            case self::STATUS_DEGRADED:
                $recommendations[] = 'Monitor closely for further degradation';
                $recommendations[] = 'Consider reducing traffic to this custodian';
                if ($failureRate > 0.5) {
                    $recommendations[] = 'Prepare for potential failover';
                }
                break;
                
            case self::STATUS_HEALTHY:
                if ($failureRate > 0.1) {
                    $recommendations[] = 'Continue monitoring for anomalies';
                }
                break;
        }
        
        return $recommendations;
    }
    
    /**
     * Check and notify if health status changed
     */
    private function checkAndNotifyHealthChange(string $custodian, string $newStatus): void
    {
        $cacheKey = "custodian:health:status:{$custodian}";
        $previousStatus = Cache::get($cacheKey);
        
        if ($previousStatus !== null && $previousStatus !== $newStatus) {
            // Status changed, fire event
            event(new CustodianHealthChanged(
                custodian: $custodian,
                previousStatus: $previousStatus,
                newStatus: $newStatus,
                timestamp: now()
            ));
            
            Log::warning("Custodian health status changed", [
                'custodian' => $custodian,
                'previous' => $previousStatus,
                'new' => $newStatus,
            ]);
        }
        
        // Update cached status
        Cache::put($cacheKey, $newStatus, 3600); // 1 hour
    }
    
    /**
     * Get custodian availability percentage over time period
     */
    public function getAvailabilityMetrics(string $custodian, int $hours = 24): array
    {
        $cacheKey = "custodian:availability:metrics:{$custodian}:{$hours}h";
        
        return Cache::remember($cacheKey, 300, function () use ($custodian, $hours) {
            // In production, this would query time-series data
            // For now, return sample metrics
            return [
                'custodian' => $custodian,
                'period_hours' => $hours,
                'availability_percentage' => 99.5,
                'total_requests' => 10000,
                'failed_requests' => 50,
                'circuit_opens' => 2,
                'average_response_time_ms' => 250,
                'p95_response_time_ms' => 500,
                'p99_response_time_ms' => 1000,
            ];
        });
    }
    
    /**
     * Get recommended custodian based on current health
     */
    public function getHealthiestCustodian(string $assetCode): ?string
    {
        $healthScores = [];
        
        foreach ($this->registry->getAllConnectorNames() as $custodian) {
            $health = $this->getCustodianHealth($custodian);
            
            // Calculate health score (0-100)
            $score = match ($health['status']) {
                self::STATUS_HEALTHY => 100 - ($health['overall_failure_rate'] ?? 0),
                self::STATUS_DEGRADED => 50 - ($health['overall_failure_rate'] ?? 0),
                self::STATUS_UNHEALTHY => 0,
                default => 0,
            };
            
            $healthScores[$custodian] = $score;
        }
        
        // Sort by health score descending
        arsort($healthScores);
        
        // Return the healthiest custodian
        $healthiest = array_key_first($healthScores);
        
        return $healthiest && $healthScores[$healthiest] > 0 ? $healthiest : null;
    }
}