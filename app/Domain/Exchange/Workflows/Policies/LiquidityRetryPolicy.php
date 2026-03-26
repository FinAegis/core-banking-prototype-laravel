<?php

namespace App\Domain\Exchange\Workflows\Policies;

use DomainException;
use InvalidArgumentException;

/**
 * NOTE: laravel-workflow v1.0.71 does not yet provide a typed RetryOptions class.
 * All retry policies are returned as associative arrays with configuration keys:
 * - initial_interval: Initial retry delay in milliseconds
 * - backoff_coefficient: Exponential backoff multiplier
 * - maximum_interval: Maximum delay cap in milliseconds
 * - maximum_attempts: Total number of retry attempts
 * - non_retryable_exceptions: Exception classes that should not trigger retries.
 *
 * When typed RetryOptions become available in a future laravel-workflow release,
 * these methods can be refactored to use the fluent builder API (see commented code).
 * For now, array-based configuration is the recommended and required approach.
 */
// use Workflow\Exception\RetryOptions;

class LiquidityRetryPolicy
{
    /**
     * @return array<string, mixed>
     */
    public static function standard(): array
    {
        return [
            'initial_interval'         => 1000, // 1 second
            'backoff_coefficient'      => 2.0,
            'maximum_interval'         => 60000, // 60 seconds
            'maximum_attempts'         => 3,
            'non_retryable_exceptions' => [
                DomainException::class,
                InvalidArgumentException::class,
            ],
        ];
        // return RetryOptions::new()
        //     ->withInitialInterval(1000) // 1 second
        //     ->withBackoffCoefficient(2.0)
        //     ->withMaximumInterval(60000) // 60 seconds
        //     ->withMaximumAttempts(3)
        //     ->withNonRetryableExceptions([
        //         \DomainException::class,
        //         \InvalidArgumentException::class,
        //     ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function external(): array
    {
        return [
            'initial_interval'         => 2000, // 2 seconds
            'backoff_coefficient'      => 2.0,
            'maximum_interval'         => 120000, // 2 minutes
            'maximum_attempts'         => 5,
            'non_retryable_exceptions' => [
                DomainException::class,
            ],
        ];
        // return RetryOptions::new()
        //     ->withInitialInterval(2000) // 2 seconds
        //     ->withBackoffCoefficient(2.0)
        //     ->withMaximumInterval(120000) // 2 minutes
        //     ->withMaximumAttempts(5)
        //     ->withNonRetryableExceptions([
        //         \DomainException::class,
        //     ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function critical(): array
    {
        return [
            'initial_interval'    => 500, // 500ms
            'backoff_coefficient' => 1.5,
            'maximum_interval'    => 30000, // 30 seconds
            'maximum_attempts'    => 10,
        ];
        // return RetryOptions::new()
        //     ->withInitialInterval(500) // 500ms
        //     ->withBackoffCoefficient(1.5)
        //     ->withMaximumInterval(30000) // 30 seconds
        //     ->withMaximumAttempts(10);
    }
}
