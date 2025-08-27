<?php

namespace App\Domain\Exchange\Workflows\Policies;

use DomainException;
use InvalidArgumentException;

// TODO: Implement RetryOptions when available in laravel-workflow package
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
