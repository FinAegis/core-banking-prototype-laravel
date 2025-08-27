<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Custodian\Exceptions\MaxRetriesExceededException;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryService
{
    /**
     * Default retry configuration.
     */
    private const DEFAULT_MAX_ATTEMPTS = 3;

    private const DEFAULT_INITIAL_DELAY_MS = 100;

    private const DEFAULT_MAX_DELAY_MS = 10000;

    private const DEFAULT_MULTIPLIER = 2.0;

    private const DEFAULT_JITTER = true;

    public function __construct(
        private readonly int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        private readonly int $initialDelayMs = self::DEFAULT_INITIAL_DELAY_MS,
        private readonly int $maxDelayMs = self::DEFAULT_MAX_DELAY_MS,
        private readonly float $multiplier = self::DEFAULT_MULTIPLIER,
        private readonly bool $jitter = self::DEFAULT_JITTER
    ) {
    }

    /**
     * Execute operation with exponential backoff retry.
     *
     * @template T
     *
     * @param  callable(): T  $operation  The operation to execute
     * @param  array<class-string<Throwable>>  $retryableExceptions  Exceptions that trigger retry
     * @param  string  $context  Context for logging
     * @return T
     *
     * @throws MaxRetriesExceededException When all retry attempts are exhausted
     * @throws Throwable When a non-retryable exception occurs
     */
    public function execute(
        callable $operation,
        array $retryableExceptions = [Exception::class],
        string $context = 'operation'
    ): mixed {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            try {
                // Execute the operation
                $result = $operation();

                // Log successful retry if not first attempt
                if ($attempt > 1) {
                    Log::info(
                        'Operation succeeded after retry',
                        [
                            'context' => $context,
                            'attempt' => $attempt,
                        ]
                    );
                }

                return $result;
            } catch (Throwable $exception) {
                $lastException = $exception;

                // Check if exception is retryable
                if (! $this->isRetryable($exception, $retryableExceptions)) {
                    throw $exception;
                }

                // Check if we have more attempts
                if ($attempt >= $this->maxAttempts) {
                    break;
                }

                // Calculate delay with exponential backoff
                $delayMs = $this->calculateDelay($attempt);

                Log::warning(
                    'Operation failed, retrying',
                    [
                        'context'      => $context,
                        'attempt'      => $attempt,
                        'max_attempts' => $this->maxAttempts,
                        'delay_ms'     => $delayMs,
                        'exception'    => $exception->getMessage(),
                    ]
                );

                // Sleep before retry
                usleep($delayMs * 1000);
            }
        }

        // All attempts exhausted
        throw new MaxRetriesExceededException(
            "Operation failed after {$this->maxAttempts} attempts: {$context}",
            0,
            $lastException
        );
    }

    /**
     * Execute operation with custom retry configuration.
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @param    array{
     *     maxAttempts?: int,
     *     initialDelayMs?: int,
     *     maxDelayMs?: int,
     *     multiplier?: float,
     *     jitter?: bool,
     *     retryableExceptions?: array<class-string<\Throwable>>,
     *     context?: string
     * } $config
     * @return T
     */
    public function executeWithConfig(callable $operation, array $config = []): mixed
    {
        $service = new self(
            maxAttempts: $config['maxAttempts'] ?? $this->maxAttempts,
            initialDelayMs: $config['initialDelayMs'] ?? $this->initialDelayMs,
            maxDelayMs: $config['maxDelayMs'] ?? $this->maxDelayMs,
            multiplier: $config['multiplier'] ?? $this->multiplier,
            jitter: $config['jitter'] ?? $this->jitter
        );

        return $service->execute(
            $operation,
            $config['retryableExceptions'] ?? [Exception::class],
            $config['context'] ?? 'operation'
        );
    }

    /**
     * Calculate delay with exponential backoff and optional jitter.
     */
    private function calculateDelay(int $attempt): int
    {
        // Base exponential backoff calculation
        $baseDelay = $this->initialDelayMs * pow($this->multiplier, $attempt - 1);

        // Cap at maximum delay
        $delay = min($baseDelay, $this->maxDelayMs);

        // Add jitter to prevent thundering herd
        if ($this->jitter) {
            // Random jitter between 0% and 25% of the delay
            $jitterAmount = $delay * (mt_rand(0, 25) / 100);
            $delay = (int) ($delay + $jitterAmount);
        }

        return (int) $delay;
    }

    /**
     * Check if exception is retryable.
     */
    private function isRetryable(Throwable $exception, array $retryableExceptions): bool
    {
        foreach ($retryableExceptions as $retryableClass) {
            if ($exception instanceof $retryableClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a retry service for network operations.
     */
    public static function forNetworkOperations(): self
    {
        return new self(
            maxAttempts: 3,
            initialDelayMs: 200,
            maxDelayMs: 5000,
            multiplier: 2.5,
            jitter: true
        );
    }

    /**
     * Create a retry service for database operations.
     */
    public static function forDatabaseOperations(): self
    {
        return new self(
            maxAttempts: 3,
            initialDelayMs: 50,
            maxDelayMs: 1000,
            multiplier: 2.0,
            jitter: true
        );
    }

    /**
     * Create a retry service for critical operations.
     */
    public static function forCriticalOperations(): self
    {
        return new self(
            maxAttempts: 5,
            initialDelayMs: 500,
            maxDelayMs: 30000,
            multiplier: 3.0,
            jitter: true
        );
    }
}
