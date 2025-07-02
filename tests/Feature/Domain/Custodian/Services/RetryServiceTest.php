<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Custodian\Services;

use App\Domain\Custodian\Services\RetryService;
use App\Domain\Custodian\Exceptions\MaxRetriesExceededException;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RetryServiceTest extends TestCase
{
    private RetryService $retryService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->retryService = new RetryService(
            maxAttempts: 3,
            initialDelayMs: 100,
            maxDelayMs: 1000,
            multiplier: 2.0,
            jitter: false // Disable jitter for predictable testing
        );
    }
    
    public function test_successful_operation_executes_once(): void
    {
        $attempts = 0;
        
        $result = $this->retryService->execute(
            operation: function () use (&$attempts) {
                $attempts++;
                return 'success';
            }
        );
        
        $this->assertEquals('success', $result);
        $this->assertEquals(1, $attempts);
    }
    
    public function test_retries_on_retryable_exception(): void
    {
        $attempts = 0;
        
        $result = $this->retryService->execute(
            operation: function () use (&$attempts) {
                $attempts++;
                
                if ($attempts < 3) {
                    throw new \RuntimeException('Transient error');
                }
                
                return 'success after retries';
            },
            retryableExceptions: [\RuntimeException::class]
        );
        
        $this->assertEquals('success after retries', $result);
        $this->assertEquals(3, $attempts);
    }
    
    public function test_throws_after_max_attempts(): void
    {
        $attempts = 0;
        
        $this->expectException(MaxRetriesExceededException::class);
        $this->expectExceptionMessage('Operation failed after 3 attempts');
        
        $this->retryService->execute(
            operation: function () use (&$attempts) {
                $attempts++;
                throw new \RuntimeException('Persistent error');
            },
            retryableExceptions: [\RuntimeException::class]
        );
        
        $this->assertEquals(3, $attempts); // Max attempts
    }
    
    public function test_does_not_retry_non_retryable_exceptions(): void
    {
        $attempts = 0;
        
        $this->expectException(\LogicException::class);
        
        $this->retryService->execute(
            operation: function () use (&$attempts) {
                $attempts++;
                throw new \LogicException('Non-retryable error');
            },
            retryableExceptions: [\RuntimeException::class] // Only RuntimeException is retryable
        );
        
        $this->assertEquals(1, $attempts); // No retries
    }
    
    public function test_exponential_backoff_delays(): void
    {
        $attempts = 0;
        $delays = [];
        $startTimes = [];
        
        try {
            $this->retryService->execute(
                operation: function () use (&$attempts, &$startTimes) {
                    $startTimes[] = microtime(true);
                    $attempts++;
                    throw new \RuntimeException('Force retry');
                },
                retryableExceptions: [\RuntimeException::class]
            );
        } catch (MaxRetriesExceededException $e) {
            // Expected
        }
        
        // Calculate actual delays
        for ($i = 1; $i < count($startTimes); $i++) {
            $delays[] = ($startTimes[$i] - $startTimes[$i - 1]) * 1000; // Convert to ms
        }
        
        // Verify exponential backoff pattern (100ms, 200ms)
        $this->assertCount(2, $delays);
        $this->assertGreaterThanOrEqual(90, $delays[0]); // ~100ms (allowing for execution time)
        $this->assertLessThan(150, $delays[0]);
        $this->assertGreaterThanOrEqual(190, $delays[1]); // ~200ms
        $this->assertLessThan(250, $delays[1]);
    }
    
    public function test_respects_max_delay(): void
    {
        // Create service with low max delay
        $service = new RetryService(
            maxAttempts: 5,
            initialDelayMs: 100,
            maxDelayMs: 150, // Cap at 150ms
            multiplier: 2.0,
            jitter: false
        );
        
        $attempts = 0;
        $startTimes = [];
        
        try {
            $service->execute(
                operation: function () use (&$attempts, &$startTimes) {
                    $startTimes[] = microtime(true);
                    $attempts++;
                    throw new \RuntimeException('Force retry');
                },
                retryableExceptions: [\RuntimeException::class]
            );
        } catch (MaxRetriesExceededException $e) {
            // Expected
        }
        
        // Calculate delays
        $delays = [];
        for ($i = 1; $i < count($startTimes); $i++) {
            $delays[] = ($startTimes[$i] - $startTimes[$i - 1]) * 1000;
        }
        
        // All delays should be capped at max delay
        foreach ($delays as $delay) {
            $this->assertLessThan(200, $delay); // Max 150ms + some overhead
        }
    }
    
    public function test_jitter_adds_randomness(): void
    {
        // Create service with jitter enabled
        $service = new RetryService(
            maxAttempts: 3,
            initialDelayMs: 1000,
            maxDelayMs: 5000,
            multiplier: 1.0, // Keep delay constant to test jitter
            jitter: true
        );
        
        $delays = [];
        
        // Run multiple times to collect jittered delays
        for ($run = 0; $run < 5; $run++) {
            $startTimes = [];
            
            try {
                $service->execute(
                    operation: function () use (&$startTimes) {
                        $startTimes[] = microtime(true);
                        throw new \RuntimeException('Force retry');
                    },
                    retryableExceptions: [\RuntimeException::class]
                );
            } catch (MaxRetriesExceededException $e) {
                // Expected
            }
            
            if (count($startTimes) >= 2) {
                $delays[] = ($startTimes[1] - $startTimes[0]) * 1000;
            }
        }
        
        // With jitter, delays should vary (not all exactly 1000ms)
        $uniqueDelays = array_unique(array_map(fn($d) => round($d), $delays));
        $this->assertGreaterThan(1, count($uniqueDelays), 'Jitter should produce varied delays');
        
        // All delays should be within jitter range (750ms - 1250ms for ±25% jitter)
        foreach ($delays as $delay) {
            $this->assertGreaterThan(700, $delay);
            $this->assertLessThan(1300, $delay);
        }
    }
    
    public function test_logs_retry_attempts(): void
    {
        Log::shouldReceive('warning')
            ->twice()
            ->with('Operation failed, retrying', \Mockery::type('array'));
        
        try {
            $this->retryService->execute(
                operation: function () {
                    throw new \RuntimeException('Test error');
                },
                retryableExceptions: [\RuntimeException::class],
                context: 'test-context'
            );
        } catch (MaxRetriesExceededException $e) {
            // Expected
        }
    }
    
    public function test_single_attempt_service(): void
    {
        $service = new RetryService(maxAttempts: 1);
        $attempts = 0;
        
        $this->expectException(MaxRetriesExceededException::class);
        
        $service->execute(
            operation: function () use (&$attempts) {
                $attempts++;
                throw new \RuntimeException('Error');
            },
            retryableExceptions: [\RuntimeException::class]
        );
        
        $this->assertEquals(1, $attempts); // No retries with maxAttempts=1
    }
}