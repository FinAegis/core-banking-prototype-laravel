<?php

namespace App\Domain\Exchange\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CircuitBreakerService
{
    private const FAILURE_THRESHOLD = 5;

    private const SUCCESS_THRESHOLD = 2;

    private const TIMEOUT = 60; // seconds

    private const HALF_OPEN_TIMEOUT = 30; // seconds

    public function call(string $service, callable $operation, array $context = []): mixed
    {
        $state = $this->getState($service);

        if ($state === 'open') {
            if ($this->shouldAttemptReset($service)) {
                $this->setState($service, 'half-open');
                Cache::forget("circuit_breaker:{$service}:half_open_attempts");
                $state = 'half-open'; // Update state for next check
            } else {
                throw new RuntimeException("Circuit breaker is OPEN for service: {$service}");
            }
        }

        if ($state === 'half-open') {
            // Allow limited traffic through
            if ($this->isHalfOpenLimitReached($service)) {
                throw new RuntimeException("Circuit breaker is HALF-OPEN with limit reached for service: {$service}");
            }
        }

        try {
            $result = $operation();
            $this->recordSuccess($service);

            return $result;
        } catch (Exception $e) {
            $this->recordFailure($service, $e, $context);
            throw $e;
        }
    }

    private function getState(string $service): string
    {
        return Cache::get("circuit_breaker:{$service}:state", 'closed');
    }

    private function setState(string $service, string $state): void
    {
        Cache::put("circuit_breaker:{$service}:state", $state, self::TIMEOUT);
        Cache::put("circuit_breaker:{$service}:state_changed_at", now()->toIso8601String(), self::TIMEOUT);

        Log::info(
            'Circuit breaker state changed',
            [
            'service'   => $service,
            'new_state' => $state,
            ]
        );
    }

    private function recordSuccess(string $service): void
    {
        $state = $this->getState($service);

        if ($state === 'half-open') {
            $successCount = Cache::increment("circuit_breaker:{$service}:success_count");

            if ($successCount >= self::SUCCESS_THRESHOLD) {
                $this->setState($service, 'closed');
                Cache::forget("circuit_breaker:{$service}:success_count");
                Cache::forget("circuit_breaker:{$service}:half_open_attempts");
                Cache::forget("circuit_breaker:{$service}:failure_count");
            } else {
                // For half-open, allow the next request by resetting attempts
                Cache::forget("circuit_breaker:{$service}:half_open_attempts");
            }
        } else {
            // For closed state, just clear failure count
            Cache::forget("circuit_breaker:{$service}:failure_count");
        }
    }

    private function recordFailure(string $service, Exception $exception, array $context): void
    {
        $state = $this->getState($service);

        if ($state === 'half-open') {
            // Failure in half-open immediately opens the circuit again
            $this->setState($service, 'open');
            Cache::forget("circuit_breaker:{$service}:half_open_attempts");
            Cache::forget("circuit_breaker:{$service}:success_count");
            Log::warning(
                'Circuit breaker failure in half-open state, reopening',
                [
                'service'   => $service,
                'exception' => $exception->getMessage(),
                'context'   => $context,
                ]
            );

            return;
        }

        $failureCount = Cache::increment("circuit_breaker:{$service}:failure_count");

        Log::warning(
            'Circuit breaker failure recorded',
            [
            'service'       => $service,
            'failure_count' => $failureCount,
            'exception'     => $exception->getMessage(),
            'context'       => $context,
            ]
        );

        if ($failureCount >= self::FAILURE_THRESHOLD && $state !== 'open') {
            $this->setState($service, 'open');
            Cache::forget("circuit_breaker:{$service}:failure_count");
        }
    }

    private function shouldAttemptReset(string $service): bool
    {
        $stateChangedAt = Cache::get("circuit_breaker:{$service}:state_changed_at");

        if (! $stateChangedAt) {
            return true;
        }

        // Handle both string and Carbon instances
        if (is_string($stateChangedAt)) {
            $timestamp = \Carbon\Carbon::parse($stateChangedAt);
        } elseif ($stateChangedAt instanceof \Carbon\Carbon) {
            $timestamp = $stateChangedAt;
        } else {
            // If it's something else, try to convert to string and parse
            $timestamp = \Carbon\Carbon::parse((string) $stateChangedAt);
        }

        return abs(now()->diffInSeconds($timestamp)) >= self::HALF_OPEN_TIMEOUT;
    }

    private function isHalfOpenLimitReached(string $service): bool
    {
        $attempts = Cache::get("circuit_breaker:{$service}:half_open_attempts", 0);

        if ($attempts >= 1) {
            return true; // Already have a request in progress
        }

        Cache::increment("circuit_breaker:{$service}:half_open_attempts");

        return false;
    }
}
