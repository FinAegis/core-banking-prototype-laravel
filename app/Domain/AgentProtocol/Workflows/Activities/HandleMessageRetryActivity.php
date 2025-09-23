<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Aggregates\A2AMessageAggregate;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Workflow\Activity;

class HandleMessageRetryActivity extends Activity
{
    private const MAX_RETRY_ATTEMPTS = 5;

    private const BASE_DELAY = 10; // seconds

    private const MAX_DELAY = 300; // 5 minutes

    public function execute(
        string $messageId,
        int $retryCount,
        string $reason,
        int $nextDelay
    ): array {
        // Validate retry count
        if ($retryCount > self::MAX_RETRY_ATTEMPTS) {
            throw new RuntimeException(
                "Maximum retry attempts ({self::MAX_RETRY_ATTEMPTS}) exceeded for message {$messageId}"
            );
        }

        // Update message aggregate with retry event
        try {
            $aggregate = A2AMessageAggregate::retrieve($messageId);
            $aggregate->retry($reason, $nextDelay, ['retryCount' => $retryCount]);
            $aggregate->persist();
        } catch (Exception $e) {
            Log::warning('Failed to update aggregate for message retry', [
                'messageId'  => $messageId,
                'retryCount' => $retryCount,
                'error'      => $e->getMessage(),
            ]);
        }

        // Store retry information in Redis
        $this->storeRetryInfo($messageId, $retryCount, $reason, $nextDelay);

        // Update retry metrics
        $this->updateRetryMetrics($messageId, $retryCount);

        // Schedule next retry if needed
        $scheduledAt = $this->scheduleNextRetry($messageId, $nextDelay);

        // Log retry attempt
        Log::info('Message retry scheduled', [
            'messageId'   => $messageId,
            'retryCount'  => $retryCount,
            'reason'      => $reason,
            'nextDelay'   => $nextDelay,
            'scheduledAt' => $scheduledAt,
        ]);

        return [
            'retriedAt'        => now()->toIso8601String(),
            'retryCount'       => $retryCount,
            'nextDelay'        => $nextDelay,
            'scheduledAt'      => $scheduledAt,
            'maxRetries'       => self::MAX_RETRY_ATTEMPTS,
            'remainingRetries' => self::MAX_RETRY_ATTEMPTS - $retryCount,
        ];
    }

    private function storeRetryInfo(
        string $messageId,
        int $retryCount,
        string $reason,
        int $nextDelay
    ): void {
        $key = "agent:message:{$messageId}:retry";
        $retryInfo = [
            'messageId'   => $messageId,
            'retryCount'  => $retryCount,
            'lastReason'  => $reason,
            'nextDelay'   => $nextDelay,
            'lastRetryAt' => now()->toIso8601String(),
            'nextRetryAt' => now()->addSeconds($nextDelay)->toIso8601String(),
        ];

        Redis::hMSet($key, $retryInfo);
        Redis::expire($key, 86400); // TTL of 24 hours

        // Add to retry history
        $historyKey = "agent:message:{$messageId}:retry:history";
        Redis::lPush($historyKey, json_encode([
            'attempt'   => $retryCount,
            'reason'    => $reason,
            'retriedAt' => now()->toIso8601String(),
            'delay'     => $nextDelay,
        ]));
        Redis::expire($historyKey, 86400);
    }

    private function updateRetryMetrics(string $messageId, int $retryCount): void
    {
        $date = now()->format('Y-m-d');
        $hour = now()->format('H');

        // Global retry metrics
        Redis::hIncrBy('agent:metrics:retries:total', $date, 1);
        Redis::hIncrBy('agent:metrics:retries:hourly', "{$date}:{$hour}", 1);

        // Per-message retry count distribution
        $bucket = match (true) {
            $retryCount === 1 => 'first_retry',
            $retryCount === 2 => 'second_retry',
            $retryCount === 3 => 'third_retry',
            default           => 'multiple_retries',
        };

        Redis::hIncrBy('agent:metrics:retries:distribution', $bucket, 1);

        // Track messages with high retry counts
        if ($retryCount >= 3) {
            Redis::sAdd('agent:messages:high_retry', $messageId);
            Redis::expire('agent:messages:high_retry', 3600); // 1 hour TTL
        }
    }

    private function scheduleNextRetry(string $messageId, int $delay): string
    {
        $scheduledAt = now()->addSeconds($delay);

        // Add to scheduled retry sorted set
        $scheduleKey = 'agent:messages:retry:schedule';
        Redis::zAdd($scheduleKey, (float) $scheduledAt->timestamp, $messageId);

        // Store schedule information
        $scheduleInfoKey = "agent:message:{$messageId}:schedule";
        Redis::hMSet($scheduleInfoKey, [
            'scheduledAt'  => $scheduledAt->toIso8601String(),
            'scheduledFor' => 'retry',
            'delay'        => $delay,
        ]);
        Redis::expire($scheduleInfoKey, $delay + 3600); // TTL of delay + 1 hour

        return $scheduledAt->toIso8601String();
    }

    /**
     * Calculate exponential backoff delay with jitter.
     */
    public static function calculateBackoffDelay(int $retryCount): int
    {
        // Exponential backoff: delay = min(base * 2^retry, maxDelay)
        $exponentialDelay = self::BASE_DELAY * pow(2, $retryCount - 1);
        $delay = min($exponentialDelay, self::MAX_DELAY);

        // Add jitter (±20% randomization)
        $jitter = $delay * 0.2;
        $delay = $delay + rand((int) -$jitter, (int) $jitter);

        return max(1, (int) $delay); // Ensure minimum 1 second delay
    }

    /**
     * Check if retry should be attempted based on error type.
     */
    public static function shouldRetry(string $errorType, int $retryCount): bool
    {
        // Don't retry if max attempts exceeded
        if ($retryCount >= self::MAX_RETRY_ATTEMPTS) {
            return false;
        }

        // Define non-retryable error types
        $nonRetryableErrors = [
            'validation_failed',
            'invalid_recipient',
            'authorization_failed',
            'message_too_large',
            'invalid_format',
            'duplicate_message',
        ];

        // Check if error is non-retryable
        if (in_array($errorType, $nonRetryableErrors, true)) {
            return false;
        }

        // All other errors are retryable
        return true;
    }
}
