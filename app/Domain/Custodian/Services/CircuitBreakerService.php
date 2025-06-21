<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Domain\Custodian\Exceptions\CircuitOpenException;

class CircuitBreakerService
{
    /**
     * Circuit breaker states
     */
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';
    
    /**
     * Default configuration
     */
    private const DEFAULT_FAILURE_THRESHOLD = 5;
    private const DEFAULT_SUCCESS_THRESHOLD = 2;
    private const DEFAULT_TIMEOUT = 60; // seconds
    private const DEFAULT_FAILURE_RATE_THRESHOLD = 0.5; // 50%
    private const DEFAULT_SAMPLE_SIZE = 10;
    
    public function __construct(
        private readonly int $failureThreshold = self::DEFAULT_FAILURE_THRESHOLD,
        private readonly int $successThreshold = self::DEFAULT_SUCCESS_THRESHOLD,
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
        private readonly float $failureRateThreshold = self::DEFAULT_FAILURE_RATE_THRESHOLD,
        private readonly int $sampleSize = self::DEFAULT_SAMPLE_SIZE
    ) {}
    
    /**
     * Execute a callable within circuit breaker protection
     *
     * @template T
     * @param string $service Service identifier (e.g., 'paysera.balance')
     * @param callable(): T $operation The operation to execute
     * @param callable(): T|null $fallback Optional fallback operation
     * @return T
     * @throws CircuitOpenException When circuit is open and no fallback provided
     * @throws \Throwable When operation fails and no fallback provided
     */
    public function execute(string $service, callable $operation, ?callable $fallback = null): mixed
    {
        $state = $this->getState($service);
        
        // If circuit is open, check if timeout has passed
        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptReset($service)) {
                $this->transitionToHalfOpen($service);
                $state = self::STATE_HALF_OPEN;
            } else {
                Log::warning("Circuit breaker open for service: {$service}");
                
                if ($fallback !== null) {
                    return $fallback();
                }
                
                throw new CircuitOpenException("Service {$service} is currently unavailable");
            }
        }
        
        try {
            // Execute the operation
            $result = $operation();
            
            // Record success
            $this->recordSuccess($service);
            
            // If half-open and successful, check if we can close the circuit
            if ($state === self::STATE_HALF_OPEN && $this->canCloseCircuit($service)) {
                $this->transitionToClosed($service);
            }
            
            return $result;
            
        } catch (\Throwable $exception) {
            // Record failure
            $this->recordFailure($service);
            
            // Check if we should open the circuit
            if ($this->shouldOpenCircuit($service)) {
                $this->transitionToOpen($service);
            }
            
            // If we have a fallback, use it
            if ($fallback !== null) {
                Log::info("Using fallback for service: {$service}", [
                    'exception' => $exception->getMessage(),
                ]);
                
                return $fallback();
            }
            
            // Otherwise, rethrow the exception
            throw $exception;
        }
    }
    
    /**
     * Get current circuit state
     */
    public function getState(string $service): string
    {
        return Cache::get($this->getStateKey($service), self::STATE_CLOSED);
    }
    
    /**
     * Check if service is available (circuit not open)
     */
    public function isAvailable(string $service): bool
    {
        return $this->getState($service) !== self::STATE_OPEN;
    }
    
    /**
     * Manually reset circuit to closed state
     */
    public function reset(string $service): void
    {
        $this->transitionToClosed($service);
        $this->clearMetrics($service);
    }
    
    /**
     * Get circuit breaker metrics
     */
    public function getMetrics(string $service): array
    {
        $recentCalls = $this->getRecentCalls($service);
        $failures = array_filter($recentCalls, fn($call) => !$call['success']);
        $successCount = count($recentCalls) - count($failures);
        $failureRate = count($recentCalls) > 0 
            ? count($failures) / count($recentCalls) 
            : 0;
        
        return [
            'state' => $this->getState($service),
            'total_calls' => count($recentCalls),
            'success_count' => $successCount,
            'failure_count' => count($failures),
            'failure_rate' => round($failureRate * 100, 2),
            'consecutive_failures' => $this->getConsecutiveFailures($service),
            'consecutive_successes' => $this->getConsecutiveSuccesses($service),
            'last_failure_time' => $this->getLastFailureTime($service),
            'circuit_opened_at' => $this->getCircuitOpenedTime($service),
        ];
    }
    
    /**
     * Record successful operation
     */
    private function recordSuccess(string $service): void
    {
        $this->recordCall($service, true);
        Cache::increment($this->getConsecutiveSuccessKey($service));
        Cache::put($this->getConsecutiveFailureKey($service), 0, $this->timeout * 2);
    }
    
    /**
     * Record failed operation
     */
    private function recordFailure(string $service): void
    {
        $this->recordCall($service, false);
        Cache::increment($this->getConsecutiveFailureKey($service));
        Cache::put($this->getConsecutiveSuccessKey($service), 0, $this->timeout * 2);
        Cache::put($this->getLastFailureKey($service), now()->timestamp, $this->timeout * 2);
    }
    
    /**
     * Record call to recent calls list
     */
    private function recordCall(string $service, bool $success): void
    {
        $recentCalls = $this->getRecentCalls($service);
        
        // Add new call
        $recentCalls[] = [
            'success' => $success,
            'timestamp' => now()->timestamp,
        ];
        
        // Keep only recent calls within sample size
        if (count($recentCalls) > $this->sampleSize) {
            $recentCalls = array_slice($recentCalls, -$this->sampleSize);
        }
        
        Cache::put($this->getRecentCallsKey($service), $recentCalls, $this->timeout * 2);
    }
    
    /**
     * Check if circuit should be opened
     */
    private function shouldOpenCircuit(string $service): bool
    {
        // Check consecutive failures
        $consecutiveFailures = $this->getConsecutiveFailures($service);
        if ($consecutiveFailures >= $this->failureThreshold) {
            return true;
        }
        
        // Check failure rate
        $recentCalls = $this->getRecentCalls($service);
        if (count($recentCalls) >= $this->sampleSize) {
            $failures = array_filter($recentCalls, fn($call) => !$call['success']);
            $failureRate = count($failures) / count($recentCalls);
            
            return $failureRate >= $this->failureRateThreshold;
        }
        
        return false;
    }
    
    /**
     * Check if circuit can be closed
     */
    private function canCloseCircuit(string $service): bool
    {
        return $this->getConsecutiveSuccesses($service) >= $this->successThreshold;
    }
    
    /**
     * Check if we should attempt to reset the circuit
     */
    private function shouldAttemptReset(string $service): bool
    {
        $openedAt = $this->getCircuitOpenedTime($service);
        
        if ($openedAt === null) {
            return true;
        }
        
        return now()->timestamp - $openedAt >= $this->timeout;
    }
    
    /**
     * Transition to closed state
     */
    private function transitionToClosed(string $service): void
    {
        Cache::put($this->getStateKey($service), self::STATE_CLOSED, $this->timeout * 2);
        Cache::forget($this->getCircuitOpenedKey($service));
        
        Log::info("Circuit breaker closed for service: {$service}");
    }
    
    /**
     * Transition to open state
     */
    private function transitionToOpen(string $service): void
    {
        Cache::put($this->getStateKey($service), self::STATE_OPEN, $this->timeout * 2);
        Cache::put($this->getCircuitOpenedKey($service), now()->timestamp, $this->timeout * 2);
        
        Log::warning("Circuit breaker opened for service: {$service}");
    }
    
    /**
     * Transition to half-open state
     */
    private function transitionToHalfOpen(string $service): void
    {
        Cache::put($this->getStateKey($service), self::STATE_HALF_OPEN, $this->timeout * 2);
        
        Log::info("Circuit breaker half-open for service: {$service}");
    }
    
    /**
     * Clear all metrics for a service
     */
    private function clearMetrics(string $service): void
    {
        Cache::forget($this->getRecentCallsKey($service));
        Cache::forget($this->getConsecutiveFailureKey($service));
        Cache::forget($this->getConsecutiveSuccessKey($service));
        Cache::forget($this->getLastFailureKey($service));
        Cache::forget($this->getCircuitOpenedKey($service));
    }
    
    /**
     * Get recent calls from cache
     */
    private function getRecentCalls(string $service): array
    {
        return Cache::get($this->getRecentCallsKey($service), []);
    }
    
    /**
     * Get consecutive failures count
     */
    private function getConsecutiveFailures(string $service): int
    {
        return (int) Cache::get($this->getConsecutiveFailureKey($service), 0);
    }
    
    /**
     * Get consecutive successes count
     */
    private function getConsecutiveSuccesses(string $service): int
    {
        return (int) Cache::get($this->getConsecutiveSuccessKey($service), 0);
    }
    
    /**
     * Get last failure timestamp
     */
    private function getLastFailureTime(string $service): ?int
    {
        $value = Cache::get($this->getLastFailureKey($service));
        return $value !== null ? (int) $value : null;
    }
    
    /**
     * Get circuit opened timestamp
     */
    private function getCircuitOpenedTime(string $service): ?int
    {
        $value = Cache::get($this->getCircuitOpenedKey($service));
        return $value !== null ? (int) $value : null;
    }
    
    // Cache key helpers
    private function getStateKey(string $service): string
    {
        return "circuit_breaker:{$service}:state";
    }
    
    private function getRecentCallsKey(string $service): string
    {
        return "circuit_breaker:{$service}:recent_calls";
    }
    
    private function getConsecutiveFailureKey(string $service): string
    {
        return "circuit_breaker:{$service}:consecutive_failures";
    }
    
    private function getConsecutiveSuccessKey(string $service): string
    {
        return "circuit_breaker:{$service}:consecutive_successes";
    }
    
    private function getLastFailureKey(string $service): string
    {
        return "circuit_breaker:{$service}:last_failure";
    }
    
    private function getCircuitOpenedKey(string $service): string
    {
        return "circuit_breaker:{$service}:opened_at";
    }
}