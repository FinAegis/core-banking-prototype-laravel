<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Custodian\Services;

use App\Domain\Custodian\Exceptions\CircuitOpenException;
use App\Domain\Custodian\Services\CircuitBreakerService;
use Exception;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\ServiceTestCase;

class CircuitBreakerServiceTest extends ServiceTestCase
{
    private CircuitBreakerService $circuitBreaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        $this->circuitBreaker = new CircuitBreakerService(
            failureThreshold: 3,
            successThreshold: 2,
            timeout: 5, // 5 seconds for faster tests
            failureRateThreshold: 0.5,
            sampleSize: 10
        );
    }

    #[Test]
    public function test_circuit_starts_closed(): void
    {
        $state = $this->circuitBreaker->getState('test-service');

        $this->assertEquals('closed', $state);
    }

    #[Test]
    public function test_successful_operations_keep_circuit_closed(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $result = $this->circuitBreaker->execute('test-service', fn () => 'success');
            $this->assertEquals('success', $result);
        }

        $state = $this->circuitBreaker->getState('test-service');
        $this->assertEquals('closed', $state);
    }

    #[Test]
    public function test_circuit_opens_after_consecutive_failures(): void
    {
        // Cause 3 consecutive failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->execute('test-service', function () {
                    throw new Exception('Test failure');
                });
            } catch (Exception $e) {
                // Expected
            }
        }

        $state = $this->circuitBreaker->getState('test-service');
        $this->assertEquals('open', $state);

        // Next call should throw CircuitOpenException
        $this->expectException(CircuitOpenException::class);
        $this->circuitBreaker->execute('test-service', fn () => 'should not execute');
    }

    #[Test]
    public function test_circuit_opens_on_failure_rate(): void
    {
        // Create a pattern that exceeds 50% failure rate
        $operations = [
            true, true, true, true, true,  // 5 successes
            false, false, false, false, false, false,  // 6 failures (>50%)
        ];

        foreach ($operations as $shouldSucceed) {
            try {
                $this->circuitBreaker->execute('test-service', function () use ($shouldSucceed) {
                    if (! $shouldSucceed) {
                        throw new Exception('Test failure');
                    }

                    return 'success';
                });
            } catch (Exception $e) {
                // Expected for failures
            }
        }

        $state = $this->circuitBreaker->getState('test-service');
        $this->assertEquals('open', $state);
    }

    #[Test]
    public function test_fallback_is_used_when_circuit_is_open(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->execute('test-service', function () {
                    throw new Exception('Test failure');
                });
            } catch (Exception $e) {
                // Expected
            }
        }

        // Now use fallback
        $result = $this->circuitBreaker->execute(
            'test-service',
            fn () => 'should not execute',
            fn () => 'fallback result'
        );

        $this->assertEquals('fallback result', $result);
    }

    #[Test]
    public function test_circuit_transitions_to_half_open_after_timeout(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->execute('test-service', function () {
                    throw new Exception('Test failure');
                });
            } catch (Exception $e) {
                // Expected
            }
        }

        $this->assertEquals('open', $this->circuitBreaker->getState('test-service'));

        // Fast forward time
        $this->travel(6)->seconds();

        // Next successful call should transition to half-open
        $result = $this->circuitBreaker->execute('test-service', fn () => 'success');
        $this->assertEquals('success', $result);

        // State should be half-open after one success
        $metrics = $this->circuitBreaker->getMetrics('test-service');
        $this->assertEquals(1, $metrics['consecutive_successes']);
    }

    #[Test]
    public function test_circuit_closes_after_success_threshold_in_half_open(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->execute('test-service', function () {
                    throw new Exception('Test failure');
                });
            } catch (Exception $e) {
                // Expected
            }
        }

        // Fast forward time
        $this->travel(6)->seconds();

        // Two successful calls should close the circuit
        for ($i = 0; $i < 2; $i++) {
            $this->circuitBreaker->execute('test-service', fn () => 'success');
        }

        $state = $this->circuitBreaker->getState('test-service');
        $this->assertEquals('closed', $state);
    }

    #[Test]
    public function test_metrics_are_tracked_correctly(): void
    {
        // Mix of successes and failures
        $operations = [
            ['success' => true, 'value' => 'result1'],
            ['success' => true, 'value' => 'result2'],
            ['success' => false, 'value' => 'error1'],
            ['success' => true, 'value' => 'result3'],
            ['success' => false, 'value' => 'error2'],
        ];

        foreach ($operations as $op) {
            try {
                $this->circuitBreaker->execute('test-service', function () use ($op) {
                    if (! $op['success']) {
                        throw new Exception($op['value']);
                    }

                    return $op['value'];
                });
            } catch (Exception $e) {
                // Expected for failures
            }
        }

        $metrics = $this->circuitBreaker->getMetrics('test-service');

        $this->assertEquals('closed', $metrics['state']);
        $this->assertEquals(5, $metrics['total_calls']);
        $this->assertEquals(3, $metrics['success_count']);
        $this->assertEquals(2, $metrics['failure_count']);
        $this->assertEquals(40.0, $metrics['failure_rate']); // 2/5 = 40%
    }

    #[Test]
    public function test_reset_clears_circuit_state(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->execute('test-service', function () {
                    throw new Exception('Test failure');
                });
            } catch (Exception $e) {
                // Expected
            }
        }

        $this->assertEquals('open', $this->circuitBreaker->getState('test-service'));

        // Reset the circuit
        $this->circuitBreaker->reset('test-service');

        // Circuit should be closed and metrics cleared
        $this->assertEquals('closed', $this->circuitBreaker->getState('test-service'));

        $metrics = $this->circuitBreaker->getMetrics('test-service');
        $this->assertEquals(0, $metrics['total_calls']);
        $this->assertEquals(0, $metrics['failure_count']);
    }

    #[Test]
    public function test_different_services_have_independent_circuits(): void
    {
        // Open circuit for service1
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->execute('service1', function () {
                    throw new Exception('Test failure');
                });
            } catch (Exception $e) {
                // Expected
            }
        }

        // service1 should be open
        $this->assertEquals('open', $this->circuitBreaker->getState('service1'));

        // service2 should still be closed
        $this->assertEquals('closed', $this->circuitBreaker->getState('service2'));

        // Can still execute on service2
        $result = $this->circuitBreaker->execute('service2', fn () => 'success');
        $this->assertEquals('success', $result);
    }
}
