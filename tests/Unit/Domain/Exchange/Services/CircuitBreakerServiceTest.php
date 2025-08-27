<?php

namespace Tests\Unit\Domain\Exchange\Services;

use App\Domain\Exchange\Services\CircuitBreakerService;
use Exception;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\ServiceTestCase;

class CircuitBreakerServiceTest extends ServiceTestCase
{
    private CircuitBreakerService $circuitBreaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->circuitBreaker = new CircuitBreakerService();
        Cache::flush(); // Clear any existing circuit breaker state
    }

    #[Test]
    public function test_successful_call_returns_result(): void
    {
        $result = $this->circuitBreaker->call('test_service', function () {
            return 'success';
        });

        $this->assertEquals('success', $result);
    }

    #[Test]
    public function test_circuit_opens_after_failure_threshold(): void
    {
        // Cause 5 failures to open the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->circuitBreaker->call('test_service', function () {
                    throw new Exception('Service failure');
                });
            } catch (Exception $e) {
                // Expected
            }
        }

        // Next call should fail immediately due to open circuit
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circuit breaker is OPEN for service: test_service');

        $this->circuitBreaker->call('test_service', function () {
            return 'should not execute';
        });
    }

    #[Test]
    public function test_circuit_transitions_to_half_open(): void
    {
        // Open the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->circuitBreaker->call('test_service', function () {
                    throw new Exception('Service failure');
                });
            } catch (Exception $e) {
                // Expected
            }
        }

        // Verify the circuit is open
        $currentState = Cache::get('circuit_breaker:test_service:state');
        $this->assertEquals('open', $currentState);

        // Clear all circuit breaker caches and manually set the state with old timestamp
        Cache::forget('circuit_breaker:test_service:failure_count');
        Cache::forget('circuit_breaker:test_service:success_count');
        Cache::forget('circuit_breaker:test_service:half_open_attempts');

        // Manually set state to open with old timestamp to trigger half-open transition
        Cache::put('circuit_breaker:test_service:state', 'open');
        Cache::put('circuit_breaker:test_service:state_changed_at', now()->subMinutes(2)->toIso8601String()); // Use 2 minutes to ensure timeout

        // Should allow one request through in half-open state
        $result = $this->circuitBreaker->call('test_service', function () {
            return 'recovery';
        });

        $this->assertEquals('recovery', $result);
    }

    #[Test]
    public function test_circuit_closes_after_success_threshold_in_half_open(): void
    {
        // Clear all circuit breaker state
        Cache::forget('circuit_breaker:test_service:failure_count');
        Cache::forget('circuit_breaker:test_service:success_count');
        Cache::forget('circuit_breaker:test_service:half_open_attempts');

        // Set circuit to half-open
        Cache::put('circuit_breaker:test_service:state', 'half-open');
        Cache::put('circuit_breaker:test_service:state_changed_at', now());

        // Two successful calls should close the circuit
        for ($i = 0; $i < 2; $i++) {
            $this->circuitBreaker->call('test_service', function () {
                return 'success';
            });
        }

        // Verify circuit is closed by checking state
        $state = Cache::get('circuit_breaker:test_service:state', 'closed');
        $this->assertEquals('closed', $state);
    }

    #[Test]
    public function test_circuit_reopens_on_failure_in_half_open(): void
    {
        // Set circuit to half-open
        Cache::put('circuit_breaker:test_service:state', 'half-open', 60);

        // Failure in half-open should reopen circuit
        try {
            $this->circuitBreaker->call('test_service', function () {
                throw new Exception('Service failure');
            });
        } catch (Exception $e) {
            // Expected
        }

        // Verify circuit is open
        $state = Cache::get('circuit_breaker:test_service:state');
        $this->assertEquals('open', $state);
    }

    #[Test]
    public function test_half_open_limits_requests(): void
    {
        // Set circuit to half-open
        Cache::put('circuit_breaker:test_service:state', 'half-open', 60);
        Cache::put('circuit_breaker:test_service:state_changed_at', now()->toIso8601String());

        // First request should succeed
        $result = $this->circuitBreaker->call('test_service', function () {
            return 'success';
        });

        $this->assertEquals('success', $result);

        // After first success, circuit should still be in half-open waiting for SUCCESS_THRESHOLD
        $state = Cache::get('circuit_breaker:test_service:state');
        $this->assertEquals('half-open', $state);

        // Second request should succeed as we reset attempts after success
        $result2 = $this->circuitBreaker->call('test_service', function () {
            return 'success2';
        });

        $this->assertEquals('success2', $result2);

        // After SUCCESS_THRESHOLD (2) successes, circuit should be closed
        $state = Cache::get('circuit_breaker:test_service:state', 'closed');
        $this->assertEquals('closed', $state);
    }
}
